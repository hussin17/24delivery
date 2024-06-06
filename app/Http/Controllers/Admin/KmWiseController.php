<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\KmWise;
use App\Models\Zone;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KmWiseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $kms = KmWise::orderBy('from')->paginate(config('default_pagination'));
        $km = '';
        if ($request->id) {
            $km = KmWise::find($request->id);
        }
        return view('admin-views.business-settings.km.index', compact('kms', 'km'));
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
        $validation = Validator::make($request->all(), [
            'from' => 'required',
            'to' => 'required',
            'shipping_price' => 'required'
        ]);

        if ($validation->fails()) {
            foreach ($validation->errors() as $key => $error) {
                Toastr::error($error);
            }
            return redirect()->back();
        }
        $store_block = new KmWise;
        $store_block->from = $request->from;
        $store_block->to = $request->to;
        $store_block->shipping_price = $request->shipping_price;
        $store_block->save();
        Toastr::success(translate('messages.successfully_added'));
        return redirect()->back();
    }

    /**
     * Display the specified resource.
     */
    public function show(KmWise $km)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(KmWise $km)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, KmWise $km)
    {
        $km->shipping_price = $request->shipping_price;
        $km->from = $request->from;
        $km->to = $request->to;
        $km->save();
        Toastr::success(translate('messages copy.successfully_updated'));
        return redirect()->route('admin.business-settings.kms.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(KmWise $km)
    {
        $km->delete();
        Toastr::success(translate('messages.successfully_removed'));
        return redirect()->back();
    }
}
