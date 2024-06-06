<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Zone;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BlockController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $store_blocks = Block::where('store_id', $request->store_id)->paginate(config('default_pagination'));
        $zones = Zone::where('parent', '=', 0)->paginate(config('default_pagination'));
        $block = '';
        if ($request->block_id) {
            $block = Block::find($request->block_id);
        }
        return view('admin-views.store.blocks.index', compact('store_blocks', 'zones', 'block'));
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
            'store_id' => 'required',
            'block_id' => 'required',
            'shipping_price' => 'required'
        ]);

        if ($validation->fails()) {
            foreach ($validation->errors() as $key => $error) {
                Toastr::error($error);
            }
            return redirect()->back();
        }
        $store_block = new Block;
        $store_block->store_id = $request->store_id;
        $store_block->block_id = $request->block_id;
        $store_block->shipping_price = $request->shipping_price;
        $store_block->save();
        Toastr::success(translate('messages.successfully_added'));
        return redirect()->back();
    }

    /**
     * Display the specified resource.
     */
    public function show(Block $block)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Block $block)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Block $block)
    {
        $block->shipping_price = $request->shipping_price;
        $block->block_id = $request->block_id;
        $block->save();
        Toastr::success(translate('messages copy.successfully_updated'));
        return redirect()->route('admin.store.blocks.index', ['store_id' => $request->store_id]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Block $block)
    {
        $block->delete();
        Toastr::success(translate('messages.successfully_removed'));
        return redirect()->back();
    }
}