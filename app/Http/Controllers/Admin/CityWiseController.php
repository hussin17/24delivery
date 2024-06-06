<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\CityWise;
use App\Models\Store;
use App\Scopes\StoreScope;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;

class CityWiseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $store_id = $request->query('store_id', 'all');
        // $category_id = $request->query('category_id', 'all');
        // $sub_category_id = $request->query('sub_category_id', 'all');
        $zone_id = $request->query('zone_id', 'all');
        // $condition_id = $request->query('condition_id', 'all');

        // $type = $request->query('type', 'all');
        $key = explode(' ', $request['search']);
        $cityWise = CityWise::withoutGlobalScope(StoreScope::class)
            ->when($request->query('module_id', null), function ($query) use ($request) {
                return $query->module($request->query('module_id'));
            })
            ->when(is_numeric($store_id), function ($query) use ($store_id) {
                return $query->where('store_id', $store_id);
            })
            // ->when(is_numeric($sub_category_id), function ($query) use ($sub_category_id) {
            //     return $query->where('category_id', $sub_category_id);
            // })
            // ->when(is_numeric($category_id), function ($query) use ($category_id) {
            //     return $query->whereHas('category', function ($q) use ($category_id) {
            //         return $q->whereId($category_id)->orWhere('parent_id', $category_id);
            //     });
            // })
            ->when(is_numeric($zone_id), function ($query) use ($zone_id) {
                return $query->whereHas('store', function ($q) use ($zone_id) {
                    return $q->where('zone_id', $zone_id);
                });
            })
            // ->when(is_numeric($condition_id), function ($query) use ($condition_id) {
            //     return $query->whereHas('pharmacy_item_details', function ($q) use ($condition_id) {
            //         return $q->where('id', $condition_id);
            //     });
            // })
            ->when($request['search'], function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->where('city', 'like', "%{$value}%");
                    }
                });
            })
            // ->where('is_approved', 1)
            // ->module(Config::get('module.current_module_id'))
            // ->type($type)
            ->latest()->paginate(config('default_pagination'));
        $store = $store_id != 'all' ? Store::findOrFail($store_id) : null;
        // $category = $category_id != 'all' ? Category::findOrFail($category_id) : null;
        // $sub_categories = $category_id != 'all' ? Category::where('parent_id', $category_id)->get(['id', 'name']) : [];
        // $condition = $condition_id != 'all' ? CommonCondition::findOrFail($condition_id) : [];
        return view('admin-views.city-wise.index', compact('cityWise', 'store'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // dd($request);
        $validator = Validator::make($request->all(), [
            'store_id' => 'required',
            'city_name' => 'required',
            'charge_value' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        };

        $city_wise = new CityWise;
        $city_wise->city = $request->city_name;
        $city_wise->store_id = $request->store_id;
        $city_wise->charge_price = $request->charge_value;
        $city_wise->save();

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // dd($request);
        // dd($request);
        $validator = Validator::make($request->all(), [
            'store_id' => 'required',
            'city_name' => 'required',
            'charge_value' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        };

        $city_wise = CityWise::find($id);
        $city_wise->city = $request->city_name;
        $city_wise->store_id = $request->store_id;
        $city_wise->charge_price = $request->charge_value;
        $city_wise->save();

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CityWise $cityWise)
    {
        $cityWise->delete();
        return redirect()->back();
    }
}
