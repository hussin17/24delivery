<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\CentralLogics\ProductLogic;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderDetail;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use App\CentralLogics\StoreLogic;
use App\Models\Admin;
use App\Models\BusinessSetting;
use App\Models\CityWise;
use App\Models\DeliveryMan;
use App\Models\DMVehicle;
use App\Models\ParcelCategory;
use App\Models\Store;
use App\Models\StoreWallet;
use App\Models\WithdrawalMethod;
use App\Models\WithdrawRequest;
use App\Models\Zone;
use DateTime;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Point;

class POSController extends Controller
{
    protected $timezone = 'Asia/Bahrain';
    public function place_order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'paid_amount' => 'required',
            'payment_method' => 'required',
            'store_id' => 'required',
            'address' => 'required',
        ]);

        // ُError Messages
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if ($request['order_type'] == 'delivery' && !Helpers::get_business_settings('home_delivery_status')) {
            return response()->json([
                'errors' => [
                    ['code' => 'order_type', 'message' => translate('messages.home_delivery_is_not_active')]
                ]
            ], 403);
        }

        $vendor = $request->vendor;
        $store = $request->vendor->stores[0];
        $schedule_at = $request->schedule_at ? Carbon::parse($request->schedule_at)->format('Y-m-d H:i:s') : now($this->timezone)->format('Y-m-d H:i:s');
        $temp_vehicle_id = $request->vehicle_id ?? 0;
        $cart = $request['cart'] ?? [];
        $address = $request['address'];
        $distance_data = $address['distance'] ?? 0;
        $total_addon_price = 0;
        $product_price = 0;
        $store_discount_amount = 0;
        $order_details = [];

        if ($temp_vehicle_id != 0) {
            $data = DMVehicle::active()->where('id', $temp_vehicle_id)->first();
        } else {
            $data = DMVehicle::active()->where(function ($query) use ($distance_data) {
                $query->where('starting_coverage_area', '<=', $distance_data)
                    ->where('maximum_coverage_area', '>=', $distance_data)
                    ->orWhere(function ($query) use ($distance_data) {
                        $query->where('starting_coverage_area', '>=', $distance_data);
                    });
            })->orderBy('starting_coverage_area')->first();
        }
        $extra_charges = (float) (isset($data) ? $data->extra_charges : 0);
        $vehicle_id = (isset($data) ? $data->id : null);

        $order = new Order();
        $order->id = 100000 + Order::all()->count() + 1;
        if (Order::find($order->id)) {
            $order->id = Order::latest()->first()->id + 1;
        }

        $order->schedule_at = $schedule_at;
        $order->scheduled = $request->schedule_at ? 1 : 0;
        $order->payment_status = $request->payment_status;
        $order->otp = rand(1000, 9999);
        $order->zone_id = $request->zone_id;
        $order->module_id = $request->header('moduleId');
        // $order->receiver_details = json_decode($request->receiver_details);
        $dateOnly = Carbon::parse($schedule_at)->format('Y-m-d');
        if ($order->scheduled == 1) { // Pre Order
            if ($request->schedule_at && $request->schedule_at < now($this->timezone)) {
                return response()->json([
                    'errors' => [
                        ['code' => 'order_time', 'message' => translate('messages.you_can_not_schedule_a_order_in_past')]
                    ]
                ], 406);
            }
            if ($this->checkMaximumOrders($dateOnly, $store->max_orders_each_day)) {
                return response()->json(['message' => translate('messages.You have exceeded your order limit for today') . ' ( ' . $dateOnly . ' )']);
            }
            $order->order_status = 'pending';
            $order->pending = now($this->timezone);
        } else {
            $order->order_status = 'confirmed';
            $order->pending = now($this->timezone);
            $order->confirmed = now($this->timezone);
        }
        $order->order_type = 'delivery';
        $order->price_from_customer = $request->price_from_customer;
        $order->order_note = $request->order_note;
        $order->payment_method = $request->payment_method;
        $order->store_id = $store->id;
        $order->user_id = $request->user_id;
        $order->delivery_charge = 0;
        $order->original_delivery_charge = 0;
        $order->created_at = now($this->timezone);
        $order->updated_at = now($this->timezone);
        if ($cart) {
            foreach ($cart as $c) {
                if (is_array($c)) {
                    $product = Item::find($c['item_id']);
                    if ($product) {
                        // echo "$product\n";
                        if (count(json_decode($product['variations'], true)) > 0) {
                            // echo $product['variations'] . " > 0 \n";
                            $price = Helpers::variation_price($product, json_encode($c['variation']));
                            // echo $price . "\n";
                        } else {
                            $price = $product['price'];
                        }

                        $product->tax = $store->tax;
                        $product = Helpers::product_data_formatting($product);
                        $addon_data = Helpers::calculate_addon_price(\App\Models\AddOn::whereIn('id', $c['add_on_ids'])->get(), $c['add_on_qtys']);
                        $or_d = [
                            'item_id' => $product->id,
                            'item_campaign_id' => null,
                            'item_details' => json_encode($product),
                            'quantity' => $c['quantity'],
                            'price' => $price,
                            'tax_amount' => Helpers::tax_calculate($product, $price),
                            'discount_on_item' => Helpers::product_discount_calculate($product, $price, $store)['discount_amount'],
                            'discount_type' => 'discount_on_product',
                            'variant' => json_encode($c['variant']),
                            'variation' => json_encode($c['variation']),
                            'add_ons' => json_encode($addon_data['addons']),
                            'total_add_on_price' => $addon_data['total_add_on_price'],
                            'created_at' => now($this->timezone),
                            'updated_at' => now($this->timezone)
                        ];
                        $total_addon_price += $or_d['total_add_on_price'];
                        $product_price += $price * $or_d['quantity'];
                        $store_discount_amount += $or_d['discount_on_item'] * $or_d['quantity'];
                        $order_details[] = $or_d;
                    } else {
                        return response()->json([
                            'errors' => [
                                ['code' => 'campaign', 'message' => 'not found!']
                            ]
                        ], 401);
                    }
                }
            }
        }

        if (isset($request['discount'])) {
            $store_discount_amount += $request['discount_type'] == 'percent' && $request['discount'] > 0 ? ((($product_price + $total_addon_price) * $request['discount']) / 100) : $request['discount'];
        }

        $store_discount_amount = round($store_discount_amount, 2);
        $total_price = $product_price + $total_addon_price - $store_discount_amount;
        $tax = isset($request['tax']) ? $request['tax'] : $store->tax;
        $total_tax_amount = ($tax > 0) ? (($total_price * $tax) / 100) : 0;
        $coupon_discount_amount = 0;
        $total_price = $product_price + $total_addon_price - $store_discount_amount - $coupon_discount_amount;

        $tax = $store->tax;
        $total_tax_amount = round(($tax > 0) ? (($total_price * $tax) / 100) : 0, 2);

        $order->dm_vehicle_id = $vehicle_id;
        $order->order_attachment = $request->has('order_attachment') ? Helpers::upload('order/', 'png', $request->file('order_attachment')) : null;
        $order->distance = $distance_data;
        $order->delivery_charge = isset($address) ? $address['delivery_fee'] + $extra_charges : 0; // 10 + 20 = 30
        $order->original_delivery_charge = isset($address) ? $address['delivery_fee'] + $extra_charges : 0;
        $order->delivery_address = isset($address) ? json_encode($address) : null;

        $order->store_discount_amount = $store_discount_amount;
        $order->total_tax_amount = $total_tax_amount;
        $order->order_amount = $request->price_from_customer ?? $total_price + $total_tax_amount + $order->delivery_charge;
        $order->module_id = $request->header('moduleId');
        $order->created_by = 'store';
        $order->delivery_man_id = $request->delivery_man_id;
        try {
            $order->save();
            foreach ($order_details as $key => $item) {
                $order_details[$key]['order_id'] = $order->id;

                if ($store_discount_amount <= 0) {
                    $order_details[$key]['discount_on_item'] = 0;
                }
            }
            OrderDetail::insert($order_details);
            $store->increment('total_order');
            $order['details_count'] = count($cart);
            $order->delivery_address = json_decode($order->delivery_address);

            // if payment method is cash on delivery
            if ($request->payment_status == 'paid') {
                $request = $request->merge(['order_amount' => $order->delivery_charge ?? 0, 'order_price' => $order->order_amount, 'vendor_id' => $vendor->id]);
                self::request_withdraw($request);
            }

            // ? If Man_id Not Null Then check if this delivery has accepted order Then get Token
            if ($order->delivery_man_id) {

                $deliveries = DeliveryMan::join('orders', 'delivery_men.id', '=', 'orders.delivery_man_id')
                    ->where('orders.order_status', 'accepted')->where('orders.store_id', $store->id)
                    ->where('delivery_men.id', $order->delivery_man_id)->first();

                if ($deliveries) {
                    $notificationData = [
                        'title' => translate('messages.order_push_title'),
                        'description' => translate('messages.new_order_push_description'),
                        'order_id' => $order['id'],
                        'module_id' => $order['module_id'],
                        'order_type' => $order['order_type'],
                        "type" => $order['order_type'],
                        'image' => '',
                    ];
                    Helpers::send_push_notif_to_device($deliveries['fcm_token'], $notificationData);
                    DB::table('user_notifications')->insert([
                        'data' => json_encode($data),
                        'delivery_man_id' => $deliveries->id,
                        'created_at' => now($this->timezone),
                        'updated_at' => now($this->timezone)
                    ]);
                }
            } else {
                $deliveries = Helpers::get_delivery_by_distance($address['latitude'], $address['longitude']);
                Helpers::send_notification_from_store_to_delivery($deliveries, $order);
            }
            return response()->json([
                'message' => translate('messages.order_placed_successfully'),
                // 'order_id' => $order->id,
                "order" => $order,
                "deliveries" => $deliveries,
                'total_ammount' => $total_price + $order->delivery_charge + $total_tax_amount
            ], 200);
        } catch (Exception $e) {
            return $e->getMessage();
        }
        Toastr::warning(translate('messages.failed_to_place_order'));
        return back();
    }

    public function request_withdraw($request)
    {
        $w = $request['vendor']?->wallet;
        $data = [
            'vendor_id' => $request['vendor_id'],
            'amount' => $request['order_amount'],
            'order_amount' => $request['order_price'],
            'transaction_note' => null,
            'withdrawal_method_id' => null,
            'withdrawal_method_fields' => null,
            'approved' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ];

        $wallet = StoreWallet::where('vendor_id', $request['vendor_id'])->first();

        $wallet->decrement('total_earning', $data['amount']);
        // $wallet->decrement('pending_withdraw', $data['amount']);

        try {
            DB::table('withdraw_requests')->insert($data);
            $w?->increment('pending_withdraw', $request['order_amount']);
            $mail_status = Helpers::get_mail_status('withdraw_request_mail_status_admin');
            if (config('mail.status') && $mail_status == '1') {
                $wallet_transaction = WithdrawRequest::where('vendor_id', $request['vendor_id'])->latest()->first();
                $admin = Admin::where('role_id', 1)->first();
                Mail::to($admin->email)->send(new \App\Mail\WithdrawRequestMail('admin_mail', $wallet_transaction));
            }
            return response()->json(['message' => translate('messages.withdraw_request_placed_successfully')], 200);
        } catch (Exception $e) {
            // info($e->getMessage());
            return response()->json($e);
        }
    }

    public function getAcceptedDelivery(Request $request): object
    {
        $store = $request->vendor->stores[0];
        $key = explode(' ', $request->search);
        $key = explode(' ', $request['search']);
        $deliveries = DeliveryMan::join('orders', 'delivery_men.id', '=', 'orders.delivery_man_id')
            ->where('orders.order_status', 'accepted')
            ->where('orders.store_id', $store->id)
            // TODO: Enable this Line
            // ->where('orders.dm_vehicle_id', $request->vehicle_id ?? $store->transport_way)
            ->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('f_name', 'like', "%{$value}%")
                        ->orWhere('l_name', 'like', "%{$value}%");
                }
            })->groupBy('delivery_men.id')->get('delivery_men.*');
        return count($deliveries) > 0 ? $deliveries : response()->json(['message' => translate('messages.deliveries_not_found')]);
    }

    public function order_list(Request $request)
    {
        $vendor = $request['vendor'];

        $orders = Order::whereHas('store.vendor', function ($query) use ($vendor) {
            $query->where('id', $vendor->id);
        })
            ->with('customer')
            ->where('order_type', 'pos')
            ->latest()
            ->get();
        $orders = Helpers::order_data_formatting($orders, true);
        return response()->json($orders, 200);
    }

    public function generate_invoice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $vendor = $request['vendor'];

        $order = Order::whereHas('store.vendor', function ($query) use ($vendor) {
            $query->where('id', $vendor->id);
        })
            ->with('customer')
            ->where('id', $request->order_id)
            ->where('order_type', 'pos')
            ->first();

        if ($order) {
            return response()->json([
                'view' => view('vendor-views.pos.order.invoice', compact('order'))->render(),
            ]);
        }

        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => translate('messages.not_found')]
            ]
        ], 404);
    }

    public function get_customers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'search' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $key = explode(' ', $request['search']);
        $data = User::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('f_name', 'like', "%{$value}%")
                    // ->orWhere('l_name', 'like', "%{$value}%")
                    ->orWhere('phone', 'like', "%{$value}%");
            }
        })
            // ->limit(8)
            ->get([DB::raw('id, CONCAT(f_name, " ", IF(l_name, l_name, ""), " (", phone ,")") as text, CONCAT(f_name, " ", IF(l_name, l_name, "")) as name, phone')]);

        $data[] = (object) ['id' => false, 'text' => translate('messages.walk_in_customer')];

        return response()->json($data);
    }

    // Added by Code
    public function customer_store(Request $request)
    {
        $request->validate([
            'f_name' => 'required',
            'phone' => 'required',
            // 'phone' => 'required',
        ]);

        $userExists = User::where('f_name', '=', $request->f_name)->where('phone', '=', $request->phone)->first();
        if (!$userExists) {
            $userExists = User::create([
                'f_name' => $request['f_name'],
                'l_name' => $request['l_name'] ?? '',
                // 'email' => $request['email'],
                'phone' => $request['phone'],
                'password' => bcrypt('password')
            ]);
        }

        $userExists = $userExists->id;

        try {
            $mail_status = Helpers::get_mail_status('registration_otp_mail_status_user');
            if (config('mail.status') && $mail_status == '1') {
                Mail::to($request->email)->send(new \App\Mail\CustomerRegistration($request->f_name . ' ' . $request->l_name ?? '', true));
            }
            return response()->json(['id' => $userExists, 'message' => translate('messages.customer_added_successfully')], 200);
        } catch (Exception $ex) {
            info($ex->getMessage());
        }
        Toastr::warning(translate('messages.failed_to_place_order'));
        return back();
    }

    public function getAreaBlocks(Request $request)
    {
        // ? Get All Blocks (By City ID) With Price
        $validator = Validator::make($request->all(), [
            'store_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $data = CityWise::where('store_id', '=', $request->store_id)
            ->when($request->search, function ($query) use ($request) {
                $query->where('city', 'like', '%' . $request->search . '%');
            })->get();
        return response($data, 200);
    }

    // checkMaximumOrders
    public function checkMaximumOrders($preOrderDate, $max_orders_each_day): bool
    {
        $ordersCount = Order::where('pre_order_datetime', $preOrderDate)->where('order_status', 'pending')->count();
        if ($ordersCount > $max_orders_each_day) {
            return true;
        }
        return false;
    }
}
