<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin;
use App\Models\Store;
use App\Models\AdminWallet;
use App\Models\DeliveryMan;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Exports\CollectCashTransactionExport;
use App\Models\AccountTransaction;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Exception;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\error;

class AccountTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $key = isset($request['search']) ? explode(' ', $request['search']) : [];
        $account_transaction = AccountTransaction::when(isset($key), function ($query) use ($key) {
            return $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('ref', 'like', "%{$value}%");
                }
            });
        })->where('type', 'collected')
            ->latest()->paginate(config('default_pagination'));
        return view('admin-views.account.index', compact('account_transaction'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request);
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:store,deliveryman',
            'method' => 'required',
            'store_id' => 'required_if:type,store',
            'deliveryman_id' => 'required_if:type,deliveryman',
            'amount' => 'required|numeric',
        ]);

        if ($request['store_id'] && $request['deliveryman_id']) {
            $validator->getMessageBag()->add('from type', 'Can not select both deliveryman and store');
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        if ($request['type'] == 'store' && $request['store_id']) {
            $store = Store::findOrFail($request['store_id']);
            $data = $store->vendor;
            $current_balance = $data->wallet ? $data->wallet->collected_cash : 0;
        } else if ($request['type'] == 'deliveryman' && $request['deliveryman_id']) {
            $data = DeliveryMan::findOrFail($request['deliveryman_id']);
            $current_balance = $data->wallet ? $data->wallet->collected_cash : 0;
        }

        if ($current_balance < $request['amount'] && $request['transaction_type'] == 'withdraw') {
            $validator->getMessageBag()->add('amount', translate('messages.insufficient_balance'));
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        $account_transaction = new AccountTransaction();
        $account_transaction->from_type = $request['type'];
        $account_transaction->from_id = $data->id;
        $account_transaction->method = $request['method'];
        $account_transaction->ref = $request['ref'];
        $account_transaction->amount = $request['amount'];
        $account_transaction->transaction_type = $request['transaction_type'] ?? 'withdraw';
        $account_transaction->current_balance = $current_balance;

        try {
            DB::beginTransaction();
            $account_transaction->save();


            if ($request->transaction_type == 'deposit') { // ايداع
                if ($request['type'] == 'deliveryman' && $request['deliveryman_id']) {
                    return redirect()->back()->with('error', translate('messages.deliveryman_can_not_deposit'));
                }
                $data->wallet->increment('total_earning', $request['amount']);
                AdminWallet::where('admin_id', Admin::where('role_id', 1)->first()->id)->decrement('manual_received', $request['amount']);
            }

            if ($request->transaction_type == 'withdraw') { // سحب
                $data->wallet->decrement('total_earning', $request['amount']);
                AdminWallet::where('admin_id', Admin::where('role_id', 1)->first()->id)->increment('manual_received', $request['amount']);
                if ($request['type'] == 'deliveryman' && $request['deliveryman_id']) {
                    $mail_status = Helpers::get_mail_status('cash_collect_mail_status_dm');
                    if (config('mail.status') && $mail_status == '1') {
                        Mail::to($data['email'])->send(new \App\Mail\CollectCashMail($account_transaction, $data['f_name']));
                    }
                }
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return $e;
        }

        return response()->json(200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $account_transaction = AccountTransaction::findOrFail($id);
        return view('admin-views.account.view', compact('account_transaction'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        AccountTransaction::where('id', $id)->delete();
        Toastr::success(translate('messages.account_transaction_removed'));
        return back();
    }

    public function export_account_transaction(Request $request)
    {
        $key = isset($request['search']) ? explode(' ', $request['search']) : [];
        $account_transaction = AccountTransaction::when(isset($key), function ($query) use ($key) {
            return $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('ref', 'like', "%{$value}%");
                }
            });
        })->where('type', 'collected')
            ->latest()->get();

        $data = [
            'account_transactions' => $account_transaction,
            'search' => $request->search ?? null,

        ];

        if ($request->type == 'excel') {
            return Excel::download(new CollectCashTransactionExport($data), 'CollectCashTransactions.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new CollectCashTransactionExport($data), 'CollectCashTransactions.csv');
        }
    }

    public function search_account_transaction(Request $request)
    {
        $key = explode(' ', $request['search']);
        $account_transaction = AccountTransaction::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('ref', 'like', "%{$value}%");
            }
        })
            ->where('type', 'collected')
            ->get();

        return response()->json([
            'view' => view('admin-views.account.partials._table', compact('account_transaction'))->render(),
            'total' => $account_transaction->count()
        ]);
    }
}
