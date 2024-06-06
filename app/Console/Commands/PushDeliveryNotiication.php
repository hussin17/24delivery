<?php

namespace App\Console\Commands;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Api\V1\Vendor\VendorController;
use App\Models\DeliveryMan;
use App\Models\DeliveryNotification;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Store;
use COM;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class PushDeliveryNotiication extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'push:notiication';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now()->format('Y-m-d H:i:s');
        $orders = Order::scheduled()->pending()->selectRaw('orders.*, DATE_SUB(orders.schedule_at, INTERVAL stores.notification_time MINUTE) as sendNotificationTime')
            ->join('stores', 'store_id', 'stores.id')
            ->whereBetween('schedule_at', [$now, DB::raw('DATE_ADD(now(), INTERVAL stores.notification_time MINUTE)')])
            ->get();
        foreach ($orders as $order) {
            $address = json_decode($order['delivery_address']);
            $order->order_status = 'confirmed';
            $order['confirmed'] = now();
            $order['scheduled'] = 0;
            $order->save();
            $deliveries = Helpers::get_delivery_by_distance($address->latitude, $address->longitude);
            Helpers::send_notification_from_store_to_delivery($deliveries, $order);
        }
    }
}
