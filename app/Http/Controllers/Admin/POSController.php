<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderDetail;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\CentralLogics\CustomerLogic;
use App\CentralLogics\ProductLogic;
use App\Mail\OrderVerificationMail;
use App\Models\Store;
use App\Mail\PlaceOrder;
use App\Models\Admin;
use App\Models\AdminWallet;
use App\Models\Block;
use App\Models\BusinessSetting;
use App\Models\DMVehicle;
use App\Models\KmWise;
use App\Models\StoreWallet;
use App\Models\WithdrawRequest;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Scopes\StoreScope;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class POSController extends Controller
{
    public function index(Request $request)
    {
        $time = Carbon::now()->toTimeString();
        $category = $request->query('category_id', 0);
        $order_by = $request->query('order_by', 0);
        $order_types = ['customer', 'store'];
        $module_id = Config::get('module.current_module_id');
        $store_id = $request->query('store_id', null);
        $categories = Category::active()->module(Config::get('module.current_module_id'))->get();
        $store = Store::active()->find($store_id);
        $keyword = $request->query('keyword', false);
        $key = explode(' ', $keyword);

        if ($request->session()->has('cart')) {
            $cart = $request->session()->get('cart', collect([]));
            if (!isset($cart['store_id']) || $cart['store_id'] != $store_id) {
                session()->forget('cart');
                session()->forget('address');
            }
        }

        $products = Item::withoutGlobalScope(StoreScope::class)->active()
            ->when($category, function ($query) use ($category) {
                $query->whereHas('category', function ($q) use ($category) {
                    return $q->whereId($category)->orWhere('parent_id', $category);
                });
            })
            ->when($keyword, function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })
            ->whereHas('store', function ($query) use ($store_id, $module_id) {
                return $query->where(['id' => $store_id, 'module_id' => $module_id]);
            })
            ->latest()->paginate(10);

        $blocks = Zone::select(
            'zones.name as city',
            'ch.name as block',
            DB::raw('IF(ch.id, ch.id, zones.id) AS id'),
            DB::raw('IF(ch.coordinates, ch.coordinates, zones.coordinates) AS coordinates')
        )->when($request->search, function ($q) use ($request) {
            $q->where('zones.name', 'like', '%' . $request->search . '%')
                ->orWhere('ch.name', 'like', '%' . $request->search . '%');
        })->join('zones as ch', 'zones.id', '=', 'ch.parent')
            ->get();

        return view('admin-views.pos.index', compact('categories', 'products', 'category', 'keyword', 'store', 'module_id', 'blocks', 'order_types', 'order_by'));
    }

    public function quick_view(Request $request)
    {
        $product = Item::withoutGlobalScope(StoreScope::class)->with('store')->findOrFail($request->product_id);
        $order_by = request()->query('order_by');

        return response()->json([
            'success' => 1,
            'view' => view('admin-views.pos._quick-view-data', compact('product', 'order_by'))->render(),
        ]);
    }

    public function quick_view_card_item(Request $request)
    {
        $product = Item::withoutGlobalScope(StoreScope::class)->findOrFail($request->product_id);
        $item_key = $request->item_key;
        $cart_item = session()->get('cart')[$item_key];

        return response()->json([
            'success' => 1,
            'view' => view('admin-views.pos._quick-view-cart-item', compact('product', 'cart_item', 'item_key'))->render(),
        ]);
    }

    public function variant_price(Request $request)
    {
        $product = Item::withoutGlobalScope(StoreScope::class)->with('store')->find($request->id);
        if ($product->module->module_type == 'food') {
            $price = $product->price;
            $addon_price = 0;
            if ($request['addon_id']) {
                foreach ($request['addon_id'] as $id) {
                    $addon_price += $request['addon-price' . $id] * $request['addon-quantity' . $id];
                }
            }
            $product_variations = json_decode($product->food_variations, true);
            if ($request->variations && count($product_variations)) {
                $price_total =  $price + Helpers::food_variation_price($product_variations, $request->variations);
                $price = $price_total - Helpers::product_discount_calculate($product, $price_total, $product->store)['discount_amount'];
            } else {
                $price = $product->price - Helpers::product_discount_calculate($product, $product->price, $product->store)['discount_amount'];
            }
        } else {

            $str = '';
            $quantity = 0;
            $price = 0;
            $addon_price = 0;

            foreach (json_decode($product->choice_options) as $key => $choice) {
                if ($str != null) {
                    $str .= '-' . str_replace(' ', '', $request[$choice->name]);
                } else {
                    $str .= str_replace(' ', '', $request[$choice->name]);
                }
            }

            if ($request['addon_id']) {
                foreach ($request['addon_id'] as $id) {
                    $addon_price += $request['addon-price' . $id] * $request['addon-quantity' . $id];
                }
            }

            if ($str != null) {
                $count = count(json_decode($product->variations));
                for ($i = 0; $i < $count; $i++) {
                    if (json_decode($product->variations)[$i]->type == $str) {
                        $price = json_decode($product->variations)[$i]->price - Helpers::product_discount_calculate($product, json_decode($product->variations)[$i]->price, $product->store)['discount_amount'];
                    }
                }
            } else {
                $price = $product->price - Helpers::product_discount_calculate($product, $product->price, $product->store)['discount_amount'];
            }
        }

        return array('price' => Helpers::format_currency(($price * $request->quantity) + $addon_price));
    }

    public function addDeliveryInfo(Request $request)
    {
        // dd($request->block_id);
        $validator = Validator::make($request->all(), [
            'contact_person_name' => 'required',
            'contact_person_number' => 'required',
            // 'floor' => 'required',
            'road' => 'required_unless:block_id,null',
            'house' => 'required_unless:block_id,null',
            'longitude' => 'required',
            'latitude' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        $address = [
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => 'delivery',
            'address' => $request->address,
            'floor' => $request->floor,
            'road' => $request->road,
            'house' => $request->house,
            'distance' => $request->distance ?? 0,
            'delivery_fee' => $request->delivery_fee ?: 0,
            'longitude' => (string)$request->longitude,
            'latitude' => (string)$request->latitude,
            // 'use_google_map' => (bool) $request->use_google_map,
            // 'block_id' => $request->block_id ?? '',
        ];

        // dd($request->all());

        /*
         * TODO: Get Distance in KM And Add To Address Array
         * TODO: Get use_google_map value from request and add to address
         */

        $request->session()->put('address', $address);

        return response()->json([
            'data' => $address,
            'view' => view('admin-views.pos._address', compact('address'))->render(),
        ]);
    }

    public function addToCart(Request $request)
    {
        $product = Item::withoutGlobalScope(StoreScope::class)->with('store')->find($request->id);
        if ($product->module->module_type == 'food') {
            $data = array();
            $data['id'] = $product->id;
            $str = '';
            $variations = [];
            $price = 0;
            $addon_price = 0;
            $variation_price = 0;

            $product_variations = json_decode($product->food_variations, true);
            if ($request->variations && count($product_variations)) {
                foreach ($request->variations  as $key => $value) {

                    if ($value['required'] == 'on' &&  isset($value['values']) == false) {
                        return response()->json([
                            'data' => 'variation_error',
                            'message' => translate('Please select items from') . ' ' . $value['name'],
                        ]);
                    }
                    if (isset($value['values'])  && $value['min'] != 0 && $value['min'] > count($value['values']['label'])) {
                        return response()->json([
                            'data' => 'variation_error',
                            'message' => translate('Please select minimum ') . $value['min'] . translate(' For ') . $value['name'] . '.',
                        ]);
                    }
                    if (isset($value['values']) && $value['max'] != 0 && $value['max'] < count($value['values']['label'])) {
                        return response()->json([
                            'data' => 'variation_error',
                            'message' => translate('Please select maximum ') . $value['max'] . translate(' For ') . $value['name'] . '.',
                        ]);
                    }
                }
                $variation_data = Helpers::get_varient($product_variations, $request->variations);
                $variation_price = $variation_data['price'];
                $variations = $request->variations;
            }
            $data['variations'] = $variations;
            $data['variant'] = $str;

            $price = $product->price + $variation_price;
            $data['variation_price'] = $variation_price;
            $data['quantity'] = $request['quantity'];
            $data['price'] = $price;
            $data['name'] = $product->name;
            $data['discount'] = Helpers::product_discount_calculate($product, $price, $product->store)['discount_amount'];
            $data['image'] = $product->image;
            $data['add_ons'] = [];
            $data['add_on_qtys'] = [];
            $data['maximum_cart_quantity'] = $product->maximum_cart_quantity;

            if ($request['addon_id']) {
                foreach ($request['addon_id'] as $id) {
                    $addon_price += $request['addon-price' . $id] * $request['addon-quantity' . $id];
                    $data['add_on_qtys'][] = $request['addon-quantity' . $id];
                }
                $data['add_ons'] = $request['addon_id'];
            }

            $data['addon_price'] = $addon_price;

            if ($request->session()->has('cart')) {
                $cart = $request->session()->get('cart', collect([]));
                if (isset($request->cart_item_key)) {
                    $cart[$request->cart_item_key] = $data;
                    $data = 2;
                } else {
                    $cart->push($data);
                }
            } else {
                $cart = collect([$data, 'store_id' => $product->store_id]);
                $request->session()->put('cart', $cart);
            }
        } else {

            $data = array();
            $data['id'] = $product->id;
            $str = '';
            $variations = [];
            $price = 0;
            $addon_price = 0;

            //Gets all the choice values of customer choice option and generate a string like Black-S-Cotton
            foreach (json_decode($product->choice_options) as $key => $choice) {
                $data[$choice->name] = $request[$choice->name];
                $variations[$choice->title] = $request[$choice->name];
                if ($str != null) {
                    $str .= '-' . str_replace(' ', '', $request[$choice->name]);
                } else {
                    $str .= str_replace(' ', '', $request[$choice->name]);
                }
            }
            $data['variations'] = $variations;
            $data['variant'] = $str;
            if ($request->session()->has('cart') && !isset($request->cart_item_key)) {
                if (count($request->session()->get('cart')) > 0) {
                    foreach ($request->session()->get('cart') as $key => $cartItem) {
                        if (is_array($cartItem) && $cartItem['id'] == $request['id'] && $cartItem['variant'] == $str) {
                            return response()->json([
                                'data' => 1
                            ]);
                        }
                    }
                }
            }
            //Check the string and decreases quantity for the stock
            if ($str != null) {
                $count = count(json_decode($product->variations));
                for ($i = 0; $i < $count; $i++) {
                    if (json_decode($product->variations)[$i]->type == $str) {
                        $price = json_decode($product->variations)[$i]->price;
                        $data['variations'] = json_decode($product->variations, true)[$i];
                    }
                }
            } else {
                $price = $product->price;
            }

            $data['quantity'] = $request['quantity'];
            $data['price'] = $price;
            $data['name'] = $product->name;
            $data['discount'] = Helpers::product_discount_calculate($product, $price, $product->store)['discount_amount'];
            $data['image'] = $product->image;
            $data['add_ons'] = [];
            $data['add_on_qtys'] = [];
            $data['maximum_cart_quantity'] = $product->maximum_cart_quantity;

            if ($request['addon_id']) {
                foreach ($request['addon_id'] as $id) {
                    $addon_price += $request['addon-price' . $id] * $request['addon-quantity' . $id];
                    $data['add_on_qtys'][] = $request['addon-quantity' . $id];
                }
                $data['add_ons'] = $request['addon_id'];
            }

            $data['addon_price'] = $addon_price;

            if ($request->session()->has('cart')) {
                $cart = $request->session()->get('cart', collect([]));

                if (!isset($cart['store_id']) || $cart['store_id'] != $product->store_id) {
                    return response()->json([
                        'data' => -1
                    ]);
                }
                if (isset($request->cart_item_key)) {
                    $cart[$request->cart_item_key] = $data;
                    $data = 2;
                } else {
                    $cart->push($data);
                }
            } else {
                $cart = collect([$data]);
                $cart->put('store_id', $product->store_id);
                $request->session()->put('cart', $cart);
            }
        }

        return response()->json([
            'data' => $data
        ]);
    }

    public function cart_items(Request $request)
    {
        $store = Store::find($request->store_id);
        $blocks = Zone::select(
            'zones.name as city',
            'ch.name as block',
            DB::raw('IF(ch.id, ch.id, zones.id) AS id'),
            DB::raw('IF(ch.coordinates, ch.coordinates, zones.coordinates) AS coordinates')
        )->when($request->search, function ($q) use ($request) {
            $q->where('zones.name', 'like', '%' . $request->search . '%')
                ->orWhere('ch.name', 'like', '%' . $request->search . '%');
        })->join('zones as ch', 'zones.id', '=', 'ch.parent')
            ->get();

            $order_by = $request->query('order_by', '');
        return view('admin-views.pos._cart', compact('store', 'blocks', 'order_by'));
    }

    //removes from Cart
    public function removeFromCart(Request $request)
    {
        if ($request->session()->has('cart')) {
            $cart = $request->session()->get('cart', collect([]));
            $cart->forget($request->key);
            $request->session()->put('cart', $cart);
        }

        return response()->json([], 200);
    }

    //updated the quantity for a cart item
    public function updateQuantity(Request $request)
    {
        $cart = $request->session()->get('cart', collect([]));
        $cart = $cart->map(function ($object, $key) use ($request) {
            if ($key == $request->key) {
                $object['quantity'] = $request->quantity;
            }
            return $object;
        });
        $request->session()->put('cart', $cart);
        return response()->json([], 200);
    }

    //empty Cart
    public function emptyCart(Request $request)
    {
        session()->forget('cart');
        session()->forget('address');
        return response()->json([], 200);
    }

    public function update_tax(Request $request)
    {
        $cart = $request->session()->get('cart', collect([]));
        $cart['tax'] = $request->tax;
        $request->session()->put('cart', $cart);
        return back();
    }

    public function update_discount(Request $request)
    {
        $cart = $request->session()->get('cart', collect([]));
        $cart['discount'] = $request->discount;
        $cart['discount_type'] = $request->type;
        $request->session()->put('cart', $cart);
        return back();
    }

    public function update_paid(Request $request)
    {
        $cart = $request->session()->get('cart', collect([]));
        $cart['paid'] = $request->paid;
        $request->session()->put('cart', $cart);
        return back();
    }

    public function get_customers(Request $request)
    {
        $key = explode(' ', $request['q']);
        $data = User::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('f_name', 'like', "%{$value}%")
                    ->orWhere('l_name', 'like', "%{$value}%")
                    ->orWhere('phone', 'like', "%{$value}%");
            }
        })
            ->limit(8)
            ->get([DB::raw('id, CONCAT(f_name, " ", IF(l_name, l_name, "") , " (", phone ,")") as text')]);

        return response()->json($data);
    }

    public function place_order(Request $request)
    {

        // dd(request()->query('order_by'));

        if (!$request->user_id) {
            Toastr::error(translate('messages.no_customer_selected'));
            return back();
        }
        if (!$request->type) {
            Toastr::error(translate('No payment method selected'));
            return back();
        }
        // if ($request->session()->has('cart')) {
        //     if (count($request->session()->get('cart')) < 2) {
        //         Toastr::error(translate('messages.cart_empty_warning'));
        //         return back();
        //     }
        // } else {
        //     Toastr::error(translate('messages.cart_empty_warning'));
        //     return back();
        // }
        if ($request->session()->has('address')) {
            $address = $request->session()->get('address');
        } else {
            if (!isset($address['delivery_fee'])) {
                Toastr::error(translate('messages.please_select_a_valid_delivery_location_on_the_map'));
                return back();
            }
            Toastr::error(translate('messages.delivery_information_warning'));
            return back();
        }
        if ($request->type == 'wallet' && Helpers::get_business_settings('wallet_status', false) != 1) {
            Toastr::error(translate('messages.customer_wallet_disable_warning'));
        }

        $distance_data = isset($address) ? $address['distance'] : 0;

        $data =  DMVehicle::active()->where(function ($query) use ($distance_data) {
            $query->where('starting_coverage_area', '<=', $distance_data)->where('maximum_coverage_area', '>=', $distance_data)
                ->orWhere(function ($query) use ($distance_data) {
                    $query->where('starting_coverage_area', '>=', $distance_data);
                });
        })->orderBy('starting_coverage_area')->first();

        $extra_charges = (float) (isset($data) ? $data->extra_charges  : 0);
        $vehicle_id = (isset($data) ? $data->id  : null);

        $store = Store::find($request->store_id);
        $cart = $request->session()->get('cart');

        $total_addon_price = 0;
        $product_price = 0;
        $store_discount_amount = 0;

        $order_details = [];
        $product_data = [];
        $order = new Order();
        $order->id = 100000 + Order::count() + 1;
        if (Order::find($order->id)) {
            $order->id = Order::latest()->first()->id + 1;
        }

        $order->distance = isset($address) ? $address['distance'] : 0;
        // $order->payment_status = $request->type == 'wallet' ? 'paid' : 'unpaid';
        $order->payment_status = $request->type ?? 'unpaid';
        $order->order_status = (($request->type == 'wallet') || ($request->type == 'paid')) ? 'confirmed' : 'pending';
        $order->order_type = 'delivery';
        $order->payment_method = $request->type;
        $order->store_id = $store->id;
        $order->module_id = $store->module_id;
        $order->user_id = $request->user_id;
        $order->dm_vehicle_id = $vehicle_id;
        $order->delivery_charge = isset($address) ? $address['delivery_fee'] + $extra_charges : 0;
        $order->original_delivery_charge = isset($address) ? $address['delivery_fee'] + $extra_charges : 0;
        $order->delivery_address = isset($address) ? json_encode($address) : null;
        $order->checked = 1;
        $order->schedule_at = $request->pre_order_datetime ?? now();
        $order->scheduled = $request->pre_order_datetime ? '1' : '0';
        $order->pre_order_datetime = $request->pre_order_datetime ?? null;
        $order->order_note = $request->order_note ?? null;
        $order->price_from_customer = $request->price_from_customer ?? null;
        $order->created_at = now();
        $order->updated_at = now();
        $order->created_by = (request()->query('order_by') ?? 'customer') . "_admin";
        $order->otp = rand(1000, 9999);
        $order->save();

        if ($cart) {
            foreach ($cart as $c) {
                if (is_array($c)) {
                    $product = Item::withoutGlobalScope(StoreScope::class)->find($c['id']);
                    if ($product) {
                        if ($product->module->module_type == 'food') {
                            if ($product->food_variations) {
                                $variation_data = Helpers::get_varient(json_decode($product->food_variations, true), $c['variations']);
                                $variations = $variation_data['variations'];
                            } else {
                                $variations = [];
                            }
                            $price = $c['price'];
                            $product->tax = $product->store->tax;
                            $product = Helpers::product_data_formatting($product);
                            $addon_data = Helpers::calculate_addon_price(\App\Models\AddOn::withOutGlobalScope(StoreScope::class)->whereIn('id', $c['add_ons'])->get(), $c['add_on_qtys']);
                            $or_d = [
                                'item_id' => $c['id'],
                                'item_campaign_id' => null,
                                'item_details' => json_encode($product),
                                'quantity' => $c['quantity'],
                                'price' => $price,
                                'tax_amount' => Helpers::tax_calculate($product, $price),
                                'discount_on_item' => Helpers::product_discount_calculate($product, $price, $product->store)['discount_amount'],
                                'discount_type' => 'discount_on_product',
                                'variant' => '',
                                'variation' => isset($variations) ? json_encode($variations) : json_encode([]),
                                // 'variation' => json_encode(count($c['variations']) ? $c['variations'] : []),
                                'add_ons' => json_encode($addon_data['addons']),
                                'total_add_on_price' => $addon_data['total_add_on_price'],
                                'created_at' => now(),
                                'updated_at' => now()
                            ];
                            $total_addon_price += $or_d['total_add_on_price'];
                            $product_price += $price * $or_d['quantity'];
                            $store_discount_amount += $or_d['discount_on_item'] * $or_d['quantity'];
                            $order_details[] = $or_d;
                        } else {

                            if (count(json_decode($product['variations'], true)) > 0) {
                                $variant_data = Helpers::variation_price($product, json_encode([$c['variations']]));
                                $price = $variant_data['price'];
                                $stock = $variant_data['stock'];
                            } else {
                                $price = $product['price'];
                                $stock = $product->stock;
                            }

                            if (config('module.' . $product->module->module_type)['stock']) {
                                if ($c['quantity'] > $stock) {
                                    Toastr::error(translate('messages.product_out_of_stock_warning', ['item' => $product->name]));
                                    return back();
                                }

                                $product_data[] = [
                                    'item' => clone $product,
                                    'quantity' => $c['quantity'],
                                    'variant' => count($c['variations']) > 0 ? $c['variations']['type'] : null
                                ];
                            }

                            $price = $c['price'];
                            $product->tax = $store->tax;
                            $product = Helpers::product_data_formatting($product);
                            $addon_data = Helpers::calculate_addon_price(\App\Models\AddOn::withoutGlobalScope(StoreScope::class)->whereIn('id', $c['add_ons'])->get(), $c['add_on_qtys']);
                            $or_d = [
                                'item_id' => $c['id'],
                                'item_campaign_id' => null,
                                'item_details' => json_encode($product),
                                'quantity' => $c['quantity'],
                                'price' => $price,
                                'tax_amount' => Helpers::tax_calculate($product, $price),
                                'discount_on_item' => Helpers::product_discount_calculate($product, $price, $store)['discount_amount'],
                                'discount_type' => 'discount_on_product',
                                'variant' => json_encode($c['variant']),
                                'variation' => json_encode(count($c['variations']) ? [$c['variations']] : []),
                                'add_ons' => json_encode($addon_data['addons']),
                                'total_add_on_price' => $addon_data['total_add_on_price'],
                                'created_at' => now(),
                                'updated_at' => now()
                            ];
                            $total_addon_price += $or_d['total_add_on_price'];
                            $product_price += $price * $or_d['quantity'];
                            $store_discount_amount += $or_d['discount_on_item'] * $or_d['quantity'];
                            $order_details[] = $or_d;
                        }
                    }
                }
            }
        }

        if (isset($cart['discount'])) {
            $store_discount_amount += $cart['discount_type'] == 'percent' && $cart['discount'] > 0 ? ((($product_price + $total_addon_price - $store_discount_amount) * $cart['discount']) / 100) : $cart['discount'];
            // dd($cart, '606');
        }

        $total_price = $product_price + $total_addon_price - $store_discount_amount;
        $tax = isset($cart['tax']) ? $cart['tax'] : $store->tax;
        // $total_tax_amount= ($tax > 0)?(($total_price * $tax)/100):0;

        $order->tax_status = 'excluded';

        $tax_included = BusinessSetting::where(['key' => 'tax_included'])->first() ?  BusinessSetting::where(['key' => 'tax_included'])->first()->value : 0;
        if ($tax_included ==  1) {
            $order->tax_status = 'included';
        }

        $total_tax_amount = Helpers::product_tax($total_price, $tax, $order->tax_status == 'included');
        $tax_a = $order->tax_status == 'included' ? 0 : $total_tax_amount;

        try {
            $order->store_discount_amount = $store_discount_amount;
            $order->tax_percentage = $tax;
            $order->total_tax_amount = $total_tax_amount;
            $order->order_amount = $request->price_from_customer ?? $total_price + $tax_a + $order->delivery_charge;
            $order->adjusment = $request->amount - ($total_price + $total_tax_amount + $order->delivery_charge);
            $order->payment_method = $request->type ?? 'cash_on_delivery';

            $order->save();

            $max_cod_order_amount = BusinessSetting::where('key', 'max_cod_order_amount')->first();
            $max_cod_order_amount_value =  $max_cod_order_amount ? $max_cod_order_amount->value : 0;
            if ($max_cod_order_amount_value > 0 && $order->payment_method == 'cash_on_delivery' && $order->order_amount > $max_cod_order_amount_value) {
                Toastr::error(translate('messages.You can not Order more then ') . $max_cod_order_amount_value . Helpers::currency_symbol() . ' ' . translate('messages.on COD order.'));
                return back();
            }

            if ($request->order_by == 'customer') {
                if ($request->type == 'wallet') {
                    if ($request->user_id) {

                        $customer = User::find($request->user_id);
                        if ($customer->wallet_balance < $order->order_amount) {
                            Toastr::error(translate('messages.insufficient_wallet_balance'));
                            return back();
                        } else {
                            CustomerLogic::create_wallet_transaction($order->user_id, $order->order_amount, 'order_place', $order->id);
                        }
                    } else {
                        Toastr::error(translate('messages.no_customer_selected'));
                        return back();
                    }
                };
            }

            if ($request->order_by == 'store') {
                if ($request->type == 'paid') {
                    if ($request->price_from_customer >= 0) {
                        $order->order_amount = $request->price_from_customer;
                        $order->save();

                        $request = $request->merge(['order_amount' => $order->order_amount, 'vendor_id' => $store->id]);
                        self::request_withdraw($request);
                    }
                }
            }

            foreach ($order_details as $key => $item) {
                $order_details[$key]['order_id'] = $order->id;
            }
            OrderDetail::insert($order_details);
            if (count($product_data) > 0) {
                foreach ($product_data as $item) {
                    ProductLogic::update_stock($item['item'], $item['quantity'], $item['variant'])->save();
                }
            }
            session()->forget('cart');
            session()->forget('address');
            session(['last_order' => $order->id]);
            Helpers::send_order_notification($order);
            $mail_status = Helpers::get_mail_status('place_order_mail_status_user');

            //PlaceOrderMail
            try {
                if ($order->order_status == 'pending' && config('mail.status') && $mail_status == '1') {
                    Mail::to($order->customer->email)->send(new PlaceOrder($order->id));
                }
                $order_verification_mail_status = Helpers::get_mail_status('order_verification_mail_status_user');
                if ($order->order_status == 'pending' && config('order_delivery_verification') == 1 && $order_verification_mail_status == '1') {
                    Mail::to($order->customer->email)->send(new OrderVerificationMail($order->otp, $order->customer->f_name));
                }
            } catch (Exception $ex) {
                info($ex->getMessage());
            }
            //PlaceOrderMail end
            Toastr::success(translate('messages.order_placed_successfully'));
            return back();
        } catch (Exception $e) {
            info(['Admin pos order error_____', $e]);
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
            'transaction_note' => null,
            'withdrawal_method_id' => null,
            'withdrawal_method_fields' => null,
            'approved' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ];

        $wallet = StoreWallet::where('vendor_id', $request['vendor_id'])->first();

        $wallet->increment('total_withdrawn', $data['amount']);
        $wallet->decrement('pending_withdraw', $data['amount']);

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
            info($e->getMessage());
            return response()->json($e);
        }
    }

    public function generate_invoice($id)
    {
        $order = Order::with(['details', 'store' => function ($query) {
            return $query->withCount('orders');
        }, 'details.item' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }, 'details.campaign' => function ($query) {
            return $query->withoutGlobalScope(StoreScope::class);
        }])->where('id', $id)->first();

        return response()->json([
            'success' => 1,
            'view' => view('admin-views.pos.invoice', compact('order'))->render(),
        ]);
    }

    public function customer_store(Request $request)
    {
        $request->validate([
            'f_name' => 'required',
            // 'l_name' => 'required',
            // 'email' => 'required|email|unique:users',
            'phone' => 'unique:users',
        ]);
        User::create([
            'f_name' => $request['f_name'],
            // 'l_name' => $request['l_name'],
            // 'email' => $request['email'],
            'phone' => $request['phone'],
            'password' => bcrypt('password')
        ]);

        try {
            $mail_status = Helpers::get_mail_status('registration_otp_mail_status_user');
            if (config('mail.status') && $mail_status == '1') {
                Mail::to($request->email)->send(new \App\Mail\CustomerRegistration($request->f_name . ' ' . $request->l_name ?? '', true));
            }
        } catch (\Exception $ex) {
            info($ex->getMessage());
        }
        Toastr::success(translate('customer_added_successfully'));
        return back();
    }

    public function extra_charge(Request $request)
    {
        // Define Variables
        $store = $request->store;
        $store = json_decode($store);
        $wise_type = $store->wiseType;
        $block_id = $request->block_id;
        $block = Zone::where('id', $block_id)->first();
        $order_by = $request->order_by;
        $lat = $request['lat'];
        $lng = $request['lng'];
        $type = $request['type'];
        $store_module = Store::where('id', $store->id)->first();

        // Get Distance By Km
        // return [$store->latitude, $store->longitude, $lat, $lng];
        $km = (float) $this->getDistanceMatrix($store->latitude, $store->longitude, $lat, $lng);
        if ($km == null) {
            Toastr::warning(translate('messages.distance_not_found'));
            return response()->json(['distance' => $km, 'shipping_price' => '0', 'extra_charge' => '0', 'message' => translate('messages.distance_not_found')], 200);
        }
        // Get Extra Charge
        $extra_charge = $this->extra_charge_by_km($km);
        if ($order_by == 'store') {
            if ($wise_type == 'km') {
                // الحالات الاستثنائية للبلوكات
                if (isset($block_id) && $block_id != null) {
                    if ($block && $block->delivery_price !== null) {
                        $data = $block->delivery_price;
                        return response()->json($data ? ['shipping_price' => (string) $data, 'extra_charge' => (string) $extra_charge, 'distance' => $km] : ['shipping_price' => '0'], 200);
                    }
                }
                if (isset($km) && $km != null) {
                    $data = KmWise::where('from', '<=', $km)->where('to', '>=', $km)->first()->shipping_price ?? '0';
                    return response()->json($data ? ['shipping_price' => (string) $data, 'extra_charge' => (string) $extra_charge, 'distance' => $km] : ['shipping_price' => '0'], 200);
                }
            }
            if ($wise_type == 'city') {
                if ($type == 'google_search') {
                    $zones = Zone::where('parent', '=', '0')->where('mode', 'sub')->get();
                    // TODO:: Make For Loop of This Line
                    $point = [$lng, $lat];
                    $zone_coordinates = $zones->pluck('coordinates')->toArray();
                    foreach ($zones as $key => $value) {
                        if (self::isPointInsidePolygon($point, $zone_coordinates[$key]['coordinates'][0])) {
                            // echo $value->name . "\n";
                            // For expected block status
                            if ($value->delivery_price > 0) {
                                return response()->json(['shipping_price' => (string) $value->delivery_price, 'extra_charge' => (string) $extra_charge, 'distance' => $km], 200);
                            }
                            $price =  Block::where('store_id', '=', $store->id)->where('block_id', '=', $value['id'])->first()->shipping_price ?? '0';
                            return response()->json($price ? ['shipping_price' => (string) $price] : ['shipping_price' => '0'], 200);
                        }
                    }
                    return response()->json(['shipping_price' => '0'], 200);
                }
                if (isset($block_id) && $block_id != null) {
                    // For expected block status
                    if ($block->delivery_price > 0) {
                        return response()->json(['shipping_price' => (string) $block->delivery_price, 'extra_charge' => (string) $extra_charge, 'distance' => $km], 200);
                    }
                    $price =  Block::where('store_id', '=', $store->id)->where('block_id', '=', $block['parent'])->first()->shipping_price ?? '0';
                    return response()->json($price ? ['shipping_price' => (string) $price] : ['shipping_price' => '0'], 200);
                }
            }
        }

        if ($order_by == 'customer') {
            $module_wise_delivery_charge = $store_module->zone->modules()->where('modules.id', $store_module->module_id)->first();
            if ($module_wise_delivery_charge) {
                $per_km_shipping_charge = $module_wise_delivery_charge->pivot->per_km_shipping_charge;
                $minimum_shipping_charge = $module_wise_delivery_charge->pivot->minimum_shipping_charge;
                $maximum_shipping_charge = $module_wise_delivery_charge->pivot->maximum_shipping_charge ?? 0;
            } else {
                $per_km_shipping_charge = (float) BusinessSetting::where(['key' => 'per_km_shipping_charge'])->first()->value;
                $minimum_shipping_charge = (float) BusinessSetting::where(['key' => 'minimum_shipping_charge'])->first()->value;
                $maximum_shipping_charge = 0;
            }

            $delivery_charge = $per_km_shipping_charge * $km;
            if ($delivery_charge < $minimum_shipping_charge) {
                $delivery_charge = $minimum_shipping_charge;
            } elseif ($maximum_shipping_charge != null && $delivery_charge > $maximum_shipping_charge) {
                $delivery_charge = $maximum_shipping_charge;
            }
            $delivery_charge = $delivery_charge + $extra_charge;
            $delivery_charge = $delivery_charge + ($delivery_charge * $store_module->zone->increaseDeliveryFee / 100);

            return response()->json(['shipping_price' => (string) number_format($delivery_charge, 3, '.', ''), 'distance' => $km], 200);
        }
    }

    public function getDistanceMatrix($storeLat, $storeLng, $lat, $lng)
    {
        // استعلام لحساب المسافة بين النقطتين
        $response = Http::get('https://router.hereapi.com/v8/routes', [
            'transportMode' => 'car',
            'origin' => $storeLat . ',' . $storeLng,
            'destination' => $lat . ',' . $lng,
            'return' => 'summary',
            'apiKey' => 'rKdZVKfHAvnCHfGP5BoLt0SRhHxT-NMNoSdpXlHHpPk',
        ]);

        $data = $response->json();
        $distance = $data['routes']['0']['sections']['0']['summary']['length'] ?? null;
        if ($distance == null) {
            return null;
        }
        return (float) $distance / 1000;
    }

    public function extra_charge_by_km(float $distance_data)
    {
        $data = DMVehicle::active()
            ->where(function ($query) use ($distance_data) {
                $query->where('starting_coverage_area', '<=', $distance_data)->where('maximum_coverage_area', '>=', $distance_data)
                    ->orWhere(function ($query) use ($distance_data) {
                        $query->where('starting_coverage_area', '>=', $distance_data);
                    });
            })->orderBy('starting_coverage_area')->first();

        return (float) (isset($data) ? $data->extra_charges  : 0);
    }

    static function isPointInsidePolygon(array $point, array $polygon)
    {
        $vertices = count($polygon);
        $intersections = 0;

        for ($i = 1; $i < $vertices; $i++) {
            $vertex1 = $polygon[$i - 1];
            $vertex2 = $polygon[$i];

            if ($vertex1[1] > $vertex2[1]) {
                $vertex1 = $polygon[$i];
                $vertex2 = $polygon[$i - 1];
            }

            if (
                $point[1] >= $vertex1[1] && $point[1] < $vertex2[1] &&
                ($point[0] < ($vertex2[0] - $vertex1[0]) * ($point[1] - $vertex1[1]) / ($vertex2[1] - $vertex1[1]) + $vertex1[0])
            ) {
                $intersections++;
            }
        }
        return $intersections % 2 != 0;
    }

    // This is Old Code
    public function extra_charge_pos(float $distance_data)
    {
        // $distance_data = $request->distancMileResult ?? 1;
        // $data =  DMVehicle::active()->where(function ($query) use ($distance_data) {
        //     $query->where('starting_coverage_area', '<=', $distance_data)->where('maximum_coverage_area', '>=', $distance_data);
        // })
        //     ->orWhere(function ($query) use ($distance_data) {
        //         $query->where('starting_coverage_area', '>=', $distance_data);
        //     })
        //     ->orderBy('starting_coverage_area')->first();

        // $extra_charges = (float) (isset($data) ? $data->extra_charges  : 0);
        // return response()->json($extra_charges, 200);
    }
}
