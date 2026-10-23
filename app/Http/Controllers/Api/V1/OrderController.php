<?php
namespace App\Http\Controllers\Api\V1;
use App\Actions\Orders\CreateOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
class OrderController extends Controller
{
    public function index(Request $request)
    {
        $data=$request->validate(['per_page'=>['sometimes','integer','min:1','max:100'],'status'=>['sometimes',Rule::enum(\App\Enums\OrderStatus::class)],'external_reference'=>['sometimes','string','max:100']]);
        $query=Order::visibleTo($request->user())->with(['items.service','invoice']);
        if (isset($data['status'])) { $query->where('status',$data['status']); }
        if (isset($data['external_reference'])) { $query->where('external_reference',$data['external_reference']); }
        return OrderResource::collection($query->latest('id')->paginate($data['per_page'] ?? 25))->additional(['meta'=>['request_id'=>$request->attributes->get('request_id')]]);
    }
    public function store(StoreOrderRequest $request, CreateOrder $action)
    {
        $key=$request->header('Idempotency-Key');
        abort_unless(is_string($key) && preg_match('/^[A-Za-z0-9._:-]{8,128}$/D',$key),422,'Idempotency-Key debe contener entre 8 y 128 caracteres válidos.');
        $order=$action->handle($request->user(),$request->validated(),$key);
        return (new OrderResource($order->load(['items.service','invoice'])))->additional(['meta'=>['request_id'=>$request->attributes->get('request_id')]])->response()->setStatusCode(201)->header('Location',url('/api/v1/orders/'.$order->public_id));
    }
    public function show(Request $request, Order $order)
    {
        Gate::authorize('view',$order);
        return (new OrderResource($order->load(['items.service','invoice'])))->additional(['meta'=>['request_id'=>$request->attributes->get('request_id')]]);
    }
}
