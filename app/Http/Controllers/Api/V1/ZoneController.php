<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Models\KmWise;
use App\Models\Zone;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\CityWise;
use App\Models\DMVehicle;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class ZoneController extends Controller
{
    public function get_zones(): JsonResponse
    {
        return response()->json(Zone::where('status', 1)->get(), 200);
    }

    public function get_blocks(Request $request)
    {
        $blocks = Zone::select(
            'zones.name as city',
            'ch.name as block',
            'zones.id as block_id',
            DB::raw('IF(ch.id, ch.id, zones.id) AS id'),
            DB::raw('IF(ch.coordinates, ch.coordinates, zones.coordinates) AS coordinates')
        )->when($request->search, function ($q) use ($request) {
            $q->where('zones.name', 'like', '%' . $request->search . '%')
                ->orWhere('ch.name', 'like', '%' . $request->search . '%');
        })->join('zones as ch', 'zones.id', '=', 'ch.parent')
            ->get();

        foreach ($blocks as $block) {
            $block['block'] = $block['block'] ?? '';
            $block['city'] = trim($block['city'] . ' ' . ($block['block'] ?? ''));
        }
        return response()->json($blocks, 200);
    }

    public function get_deliveryPrice(Request $request)
    {
        $store_id = (int) $request['vendor']->stores[0]->id ?? '';
        $store = (object) $request['vendor']->stores[0];
        $type = (string) $request['type'];
        $point = [$request['longitude'], $request['latitude']];



        // TODO: Check if Store (KM OR City) wise
        $wise_type = (string) $store->wiseType;
        $block_id = (int) $request['block_id'];

        if ($wise_type == 'km') {
            $km = (float) $request->km;
            if (isset($km) && $km != null) {
                $zone = Zone::where('id', $block_id)->first();
                if ($zone && $zone->delivery_price !== null) {
                    $data = $zone->delivery_price; // الحالة المستثناه
                } else {
                    $data = KmWise::where('from', '<=', $km)->where('to', '>=', $km)->latest()->first()->shipping_price ?? '0';
                }
                $extra_charge = $this->extra_charge($km);
                return response()->json($data ? ['shipping_price' => (string) $data, 'extra_charge' => (string) $extra_charge] : ['shipping_price' => '0'], 200);
            }
        } else {
            if ($type == 'google_search') {
                $zones = Zone::where('parent', '>', '0')->get();
                // TODO:: Make For Loop of This Line
                $zone_coordinates = $zones->pluck('coordinates')->toArray();
                foreach ($zones as $key => $value) {
                    if (self::isPointInsidePolygon($point, $zone_coordinates[$key]['coordinates'][0])) {
                        // For expected block status
                        if ($value->delivery_price > 0) {
                            return response()->json(['shipping_price' => (string) $value->delivery_price], 200);
                        }
                        $price =  Block::where('store_id', '=', $store->id)->where('block_id', '=', $value['parent'])->first()->shipping_price ?? '0';
                        return response()->json($price ? ['shipping_price' => (string) $price] : ['shipping_price' => '0'], 200);
                    }
                }
                return response()->json(['shipping_price' => '0'], 200);
            }
            if (isset($block_id) && $block_id != null) {
                $price =  Block::where('store_id', '=', $store_id)->where('block_id', '=', $request->block_id)->first()->shipping_price ?? '0';
                return response()->json($price ? ['shipping_price' => (string) $price] : ['shipping_price' => '0'], 200);
            }
        }

        return response()->json(['error' => 'paremeters is requierd'], 401);
    }

    public function extra_charge(float $distance_data)
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

    public function getdeliveryPriceCustomer(Request $request)
    {
        $km = (float) $request->km;
        if (isset($km) && $km != null) {
            $data = KmWise::where('from', '<=', $km)->where('to', '>=', $km)->first()->shipping_price ?? '0';
            $extra_charge = $this->extra_charge($km);
            return response()->json($data ? ['shipping_price' => (string) $data, 'extra_charge' => (string) $extra_charge] : ['shipping_price' => '0'], 200);
        }
        return response()->json(['message' => 'Error Message KM is required']);
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
}
