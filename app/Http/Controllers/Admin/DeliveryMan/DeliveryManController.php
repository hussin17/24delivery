<?php

namespace App\Http\Controllers\Admin\DeliveryMan;

use App\Contracts\Repositories\ConversationRepositoryInterface;
use App\Contracts\Repositories\DeliveryManRepositoryInterface;
use App\Contracts\Repositories\DmReviewRepositoryInterface;
use App\Contracts\Repositories\MessageRepositoryInterface;
use App\Contracts\Repositories\OrderTransactionRepositoryInterface;
use App\Contracts\Repositories\TranslationRepositoryInterface;
use App\Contracts\Repositories\UserInfoRepositoryInterface;
use App\Contracts\Repositories\UserNotificationRepositoryInterface;
use App\Contracts\Repositories\ZoneRepositoryInterface;
use App\Enums\ExportFileNames\Admin\DeliveryMan;
use App\Enums\ViewPaths\Admin\DeliveryMan as DeliveryManViewPath;
use App\Exports\DeliveryManEarningExport;
use App\Exports\DeliveryManListExport;
use App\Exports\DeliveryManReviewExport;
use App\Exports\DisbursementHistoryExport;
use App\Exports\SingleDeliveryManReviewExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\DeliveryManAddRequest;
use App\Http\Requests\Admin\DeliveryManUpdateRequest;
use App\Mail\DmSelfRegistration;
use App\Mail\DmSuspendMail;
use App\Models\DisbursementDetails;
use App\Services\DeliveryManService;
use App\Traits\NotificationTrait;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DeliveryManController extends BaseController
{
    use NotificationTrait;
    public function __construct(
        protected DeliveryManRepositoryInterface $deliveryManRepo,
        protected ZoneRepositoryInterface $zoneRepo,
        protected TranslationRepositoryInterface $translationRepo,
        protected DmReviewRepositoryInterface $dmReviewRepo,
        protected UserInfoRepositoryInterface $userInfoRepo,
        protected ConversationRepositoryInterface $conversationRepo,
        protected MessageRepositoryInterface $messageRepo,
        protected DeliveryManService $deliveryManService,
    )
    {
    }

    public function index(?Request $request): View|Collection|LengthAwarePaginator|null
    {
        return $this->getListView($request);
    }
    private function getListView(Request $request): View
    {
        $zoneId = $request->query('zone_id', 'all');
        $deliveryMen = $this->deliveryManRepo->getZoneWiseListWhere(
            zoneId: $zoneId,
            searchValue: $request['search'],
            filters: ['type' => 'zone_wise','application_status' => 'approved'],
            relations: ['zone'],
            dataLimit: config('default_pagination')
        );
        $zone = is_numeric($zoneId) ? $this->zoneRepo->getFirstWhere(params: ['id'=>$zoneId]) : null;
        return view(DeliveryManViewPath::LIST[VIEW], compact('deliveryMen','zone'));
    }

    public function getAddView(): View
    {
        $language = getWebConfig('language');
        $defaultLang = str_replace('_', '-', app()->getLocale());
        return view(DeliveryManViewPath::ADD[VIEW], compact('language','defaultLang'));
    }

    public function getNewDeliveryManView(Request $request): View
    {
        $searchBy = $request->query('search_by');
        $zoneId = $request->query('zone_id', 'all');
        $deliveryMen = $this->deliveryManRepo->getZoneWiseListWhere(
            zoneId: $zoneId,
            searchValue: $searchBy,
            filters: ['type' => 'zone_wise','application_status' => 'pending'],
            relations: ['zone'],
            dataLimit: config('default_pagination')
        );
        $zone = is_numeric($zoneId) ? $this->zoneRepo->getFirstWhere(params: ['id'=>$zoneId]) : null;
        return view(DeliveryManViewPath::NEW[VIEW], compact('deliveryMen','zone','searchBy'));
    }

    public function getDeniedDeliveryManView(Request $request): View
    {
        $searchBy = $request->query('search_by');
        $zoneId = $request->query('zone_id', 'all');
        $deliveryMen = $this->deliveryManRepo->getZoneWiseListWhere(
            zoneId: $zoneId,
            searchValue: $searchBy,
            filters: ['type' => 'zone_wise','application_status' => 'denied'],
            relations: ['zone'],
            dataLimit: config('default_pagination')
        );
        $zone = is_numeric($zoneId) ? $this->zoneRepo->getFirstWhere(params: ['id'=>$zoneId]) : null;
        return view(DeliveryManViewPath::DENY[VIEW], compact('deliveryMen','zone','searchBy'));
    }

    public function getSearchList(Request $request): JsonResponse
    {
        $deliveryMen = $this->deliveryManRepo->getListWhere(
            searchValue: $request['search'],
            filters: ['type' => 'zone_wise','application_status' => 'approved'],
        );
        return response()->json([
            'view'=>view(DeliveryManViewPath::SEARCH[VIEW],compact('deliveryMen'))->render(),
            'count'=>$deliveryMen->count()
        ]);
    }

    public function getActiveSearchList(Request $request): JsonResponse
    {
        $deliveryMen = $this->deliveryManRepo->getActiveFirstWhere(
            searchValue: $request['search'],
            filters: ['type' => 'zone_wise'],
        );
        return response()->json([
            'dm'=>$deliveryMen
        ]);
    }

    public function add(DeliveryManAddRequest $request): Application|Redirector|RedirectResponse
    {
        $this->deliveryManRepo->add(data: $this->deliveryManService->getAddData(request: $request));
        Toastr::success(translate('messages.deliveryman_added_successfully'));
        return back();
    }

    public function getUpdateView(string|int $id): View
    {
        $deliveryMan = $this->deliveryManRepo->getFirstWithoutGlobalScopeWhere(params: ['id' => $id]);
        $language = getWebConfig('language');
        $defaultLang = str_replace('_', '-', app()->getLocale());
        return view(DeliveryManViewPath::UPDATE[VIEW], compact('deliveryMan','language','defaultLang'));
    }

    public function update(DeliveryManUpdateRequest $request, $id): Application|Redirector|RedirectResponse
    {
        $deliveryMan = $this->deliveryManRepo->getFirstWhere(params: ['id' => $id]);
        $deliveryMan = $this->deliveryManRepo->update(id: $id ,data: $this->deliveryManService->getUpdateData(request: $request, deliveryMan: $deliveryMan));
        if($deliveryMan->userinfo) {
            $this->userInfoRepo->update(id: $deliveryMan->userinfo->id,data: [
                'f_name' => $deliveryMan->f_name,
                'l_name' => $deliveryMan->l_name,
                'email' => $deliveryMan->email,
                'image' => $deliveryMan->image,
            ]);
        }

        Toastr::success(translate('messages.deliveryman_updated_successfully'));
        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        $this->deliveryManRepo->delete(id: $request['id']);
        Toastr::success(translate('messages.deliveryman_deleted_successfully'));
        return back();
    }

    public function updateStatus(Request $request,UserNotificationRepositoryInterface $notificationRepo): RedirectResponse
    {
        $deliveryMan = $this->deliveryManRepo->update(id: $request['id'] ,data: ['status'=>$request['status']]);


            if($request['status'] == 0)
            {   $deliveryMan->auth_token = null;
                if(isset($deliveryMan->fcm_token))
                {
                    $data = [
                        'title' => translate('messages.suspended'),
                        'description' => translate('messages.your_account_has_been_suspended'),
                        'order_id' => '',
                        'image' => '',
                        'type'=> 'block'
                    ];
                    $this->sendPushNotificationToDevice($deliveryMan->fcm_token, $data);

                    $notificationRepo->add([
                        'data'=> json_encode($data),
                        'delivery_man_id'=>$deliveryMan->id,
                        'created_at'=>now(),
                        'updated_at'=>now()
                    ]);
                }
                else{
                    Toastr::warning(translate('messages.push_notification_failed'));
                }
                try {
                    $mail_status = getWebConfigStatus('suspend_mail_status_dm');
                    if (config('mail.status') && $mail_status == '1') {
                        Mail::to($deliveryMan['email'])->send(new DmSuspendMail($deliveryMan['f_name']));
                    }
                }  catch (Exception) {
                    Toastr::warning(translate('messages.failed_to_send_mail'));
                }

            }

        Toastr::success(translate('messages.deliveryman_status_updated'));
        return back();
    }
    public function updateEarning(Request $request): RedirectResponse
    {
        $this->deliveryManRepo->update(id: $request['id'] ,data: ['earning'=>$request['status']]);
        Toastr::success(translate('messages.deliveryman_type_updated'));
        return back();
    }

    public function exportList(Request $request): BinaryFileResponse
    {
        $zoneId = $request->query('zone_id', 'all');
        $deliveryMen = $this->deliveryManRepo->getZoneWiseListWhere(
            zoneId: $zoneId,
            searchValue: $request['search'],
            filters: ['type' => 'zone_wise','application_status' => 'approved'],
            relations: ['zone']
        );
        $zone = is_numeric($zoneId) ? $this->zoneRepo->getFirstWhere(params: ['id'=>$zoneId]) : null;

        $data = [
            'delivery_men'=>$deliveryMen,
            'search'=>$request->search??null,
            'zone'=>is_numeric($zoneId)?$zone['name']:null,
        ];

        if ($request['type'] == 'excel') {
            return Excel::download(new DeliveryManListExport($data), DeliveryMan::EXPORT_XLSX);
        }
        return Excel::download(new DeliveryManListExport($data), DeliveryMan::EXPORT_CSV);
    }

    public function getReviewListView(Request $request): View
    {
        $reviews = $this->dmReviewRepo->getListWhere(searchValue: $request['search'],relations: ['delivery_man','customer'],dataLimit: config('default_pagination'));
        return view(DeliveryManViewPath::REVIEW_LIST[VIEW],compact('reviews'));
    }

    public function getReviewSearchList(Request $request): JsonResponse
    {
        $reviews = $this->dmReviewRepo->getListWhere(searchValue: $request['search'],relations: ['delivery_man','customer']);

        return response()->json([
            'view' => view(DeliveryManViewPath::REVIEW_SEARCH_LIST[VIEW], compact('reviews'))->render(),
            'count' => $reviews->count()
        ]);
    }

    public function getAllReviewExportList(Request $request): BinaryFileResponse
    {
        $reviews = $this->dmReviewRepo->getListWhere(searchValue: $request['search'],relations: ['delivery_man','customer']);
        $data = [
            'reviews'=>$reviews,
            'search'=>$request->search??null,
        ];

        if ($request['type'] == 'excel') {
            return Excel::download(new DeliveryManReviewExport($data), DeliveryMan::REVIEW_EXPORT_XLSX);
        }
        return Excel::download(new DeliveryManReviewExport($data), DeliveryMan::EXPORT_CSV);

    }

    public function updateReviewStatus(Request $request): RedirectResponse
    {
        $this->dmReviewRepo->update(id: $request['id'] ,data: ['status'=>$request['status']]);
        Toastr::success(translate('messages.review_visibility_updated'));
        return back();
    }

    public function getReviewExportList(Request $request): BinaryFileResponse
    {
        $deliveryMan = $this->deliveryManRepo->getFirstWhere(params: ['type' => 'zone_wise','id' => $request['id']], relations: ['reviews']);
        $reviews = $this->dmReviewRepo->getListWhere(searchValue: $request['search'],filters: ['delivery_man_id' => $request['id']]);

        $data = [
            'dm'=>$deliveryMan,
            'reviews'=>$reviews,
            'search'=>$request->search??null,
        ];

        if ($request['type'] == 'excel') {
            return Excel::download(new SingleDeliveryManReviewExport($data), DeliveryMan::REVIEW_EXPORT_XLSX);
        }
        return Excel::download(new SingleDeliveryManReviewExport($data), DeliveryMan::EXPORT_CSV);

    }

    public function getPreview(Request $request, int|string $id, string $tab='info'): View
    {
        $deliveryMan = $this->deliveryManRepo->getFirstWhere(params: ['type' => 'zone_wise','id' => $id], relations: ['reviews']);
        if($tab == 'info')
        {
            $reviews = $this->dmReviewRepo->getListWhere(filters: ['delivery_man_id'=>$id], dataLimit: config('default_pagination'));
            return view(DeliveryManViewPath::INFO[VIEW], compact('deliveryMan', 'reviews'));
        }
        else if($tab == 'transaction')
        {
            $date = $request->query('date');
            return view(DeliveryManViewPath::TRANSACTION[VIEW], compact('deliveryMan', 'date'));
        } else if ($tab == 'disbursement') {
            $key = explode(' ', $request['search']);
            $disbursements=DisbursementDetails::where('delivery_man_id', $deliveryMan->id)
                ->when(isset($key), function ($q) use ($key){
                    $q->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('disbursement_id', 'like', "%{$value}%")
                                ->orWhere('status', 'like', "%{$value}%");
                        }
                    });
                })
                ->latest()->paginate(config('default_pagination'));
            return view('admin-views.delivery-man.view.disbursement', compact('deliveryMan','disbursements'));

        }

        $user = $this->userInfoRepo->getFirstWhere(params: ['deliveryman_id' => $id]);
        if($user){
            $conversations = $this->conversationRepo->getListWithScope(relations: ['sender', 'receiver', 'last_message'],dataLimit: 8, scopes: ['WhereUser' => [$user['id']]]);
        }else{
            $conversations = [];
        }

        return view(DeliveryManViewPath::CONVERSATION[VIEW], compact('conversations','deliveryMan'));

    }

    public function getEarningListExport(Request $request, OrderTransactionRepositoryInterface $orderTransactionRepo): BinaryFileResponse
    {
        $deliveryMan = $this->deliveryManRepo->getFirstWhere(params: ['type' => 'zone_wise','id' => $request['id']], relations: ['reviews']);
        $earnings=$orderTransactionRepo->getDmEarningList(request: $request);

        $data = [
            'dm'=>$deliveryMan,
            'earnings'=>$earnings,
            'date'=>$request->date??null,
        ];

        if ($request['type'] == 'excel') {
            return Excel::download(new DeliveryManEarningExport($data), 'DeliveryManEarnings.xlsx');
        }
        return Excel::download(new DeliveryManEarningExport($data), 'DeliveryManEarnings.csv');

    }

    public function getDropdownList(Request $request): JsonResponse
    {
        $data = $this->deliveryManRepo->getDropdownList(request: $request);
        return response()->json($data);
    }

    public function getAccountData(Request $request): JsonResponse
    {
        $deliveryMan = $this->deliveryManRepo->getFirstWhere(params: ['id' => $request['id']]);
        $wallet = $deliveryMan['wallet'];
        $cashInHand = 0;
        $balance = 0;

        if($wallet)
        {
            $cashInHand = $wallet->collected_cash;
            $balance = round($wallet->total_earning - $wallet->total_withdrawn - $wallet->pending_withdraw, config('round_up_to_digit'));
        }
        return response()->json(['cash_in_hand'=>$cashInHand, 'earning_balance'=>$balance]);

    }

    public function getConversationList(Request $request): JsonResponse
    {
        $user = $this->userInfoRepo->getFirstWhere(params: ['deliveryman_id' => $request['user_id']]);
        $deliveryMan = $this->deliveryManRepo->getFirstWhere(params: ['id' => $request['user_id']]);
        if($user){
            $conversations = $this->conversationRepo->getDmConversationList(request: $request,dataLimit: 8);
        }else{
            $conversations = [];
        }
        $view = view(DeliveryManViewPath::CONVERSATION_LIST[VIEW],compact('conversations','deliveryMan'))->render();

        return response()->json(['html'=>$view]);

    }

    public function getConversationView($conversation_id,$user_id): JsonResponse
    {
        $conversations = $this->messageRepo->getListWhere(filters: ['conversation_id' => $conversation_id]);
        $conversation = $this->conversationRepo->getFirstWhere(params: ['id'=>$conversation_id],relations: ['receiver','sender']);
        $receiver = $conversation['receiver'];
        $user = $this->userInfoRepo->getFirstWhere(params: ['id'=>$user_id]);
        return response()->json([
            'view' => view(DeliveryManViewPath::CONVERSATIONS[VIEW], compact('conversations', 'user', 'receiver'))->render()
        ]);
    }

    public function updateApplication(Request $request): RedirectResponse
    {
        $deliveryMan = $this->deliveryManRepo->update(id: $request['id'] ,data: ['application_status'=>$request['status']]);
        if($request['status'] == 'approved') $this->deliveryManRepo->update(id: $request['id'] ,data: ['status'=>1]);
        try{
            if($request['status']=='approved'){

                $mail_status = getWebConfigStatus('approve_mail_status_dm');
                if(config('mail.status') && $mail_status == '1'){
                    Mail::to($deliveryMan->email)->send(new DmSelfRegistration('approved',$deliveryMan->f_name.' '.$deliveryMan->l_name));
                }
            }else{

                $mail_status = getWebConfigStatus('deny_mail_status_dm');
                if(config('mail.status') && $mail_status == '1'){
                    Mail::to($deliveryMan->email)->send(new DmSelfRegistration('denied', $deliveryMan->f_name.' '.$deliveryMan->l_name));
                }
            }
        }catch(Exception $ex){
            info($ex->getMessage());
        }
        Toastr::success(translate('messages.application_status_updated_successfully'));
        return back();
    }

    public function disbursement_export(Request $request,$id,$type)
    {
        $key = explode(' ', $request['search']);

        $dm= \App\Models\DeliveryMan::find($id);
        $disbursements=DisbursementDetails::where('delivery_man_id', $dm->id)
            ->when(isset($key), function ($q) use ($key){
                $q->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('disbursement_id', 'like', "%{$value}%")
                            ->orWhere('status', 'like', "%{$value}%");
                    }
                });
            })
            ->latest()->get();
        $data = [
            'disbursements'=>$disbursements,
            'search'=>$request->search??null,
            'delivery_man'=>$dm->f_name.' '.$dm->l_name,
            'type'=>'dm',
        ];

        if ($request->type == 'excel') {
            return Excel::download(new DisbursementHistoryExport($data), 'Disbursementlist.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new DisbursementHistoryExport($data), 'Disbursementlist.csv');
        }
    }
}
