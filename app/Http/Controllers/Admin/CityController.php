<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Store;
use App\Scopes\ZoneScope;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $store = Store::find($request->store_id);
        $cities = City::latest()->when($request->search, function ($query) use ($request) {
            return $query->where('name', 'like', '%' . $request->search . '%');
        })->paginate(config('default_pagination'));
        return view('admin-views.cities.index', compact('cities', 'store'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin-views.cities.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'required'
        ]);
        if ($validation->fails()) {
            Toastr::error(translate('messages.name Failed'));
            return redirect()->back();
        }
        $city = new City();
        $city->name = $request->name;
        $city->save();
        Toastr::success(translate('messages.create successfully'));
        return back();
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
