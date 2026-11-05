<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\CreateOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $data = array_merge(OrderFilters::validate($request), $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'external_reference' => ['sometimes', 'string', 'max:100']]));
        $query = OrderFilters::apply(Order::visibleTo($request->user()), $data, $request->user())->with(['account', 'items.service', 'invoice' => fn ($q) => $q->withSum('payments', 'amount')]);
        if (isset($data['external_reference'])) {
            $query->where('external_reference', $data['external_reference']);
        }

        return OrderResource::collection($query->latest('id')->paginate($data['per_page'] ?? 25))->additional(['meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    public function store(StoreOrderRequest $request, CreateOrder $action)
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || ! preg_match('/^[A-Za-z0-9._:-]{8,128}$/D', $key)) {
            throw ValidationException::withMessages(['Idempotency-Key' => 'Idempotency-Key debe contener entre 8 y 128 caracteres válidos.']);
        }
        $order = $action->handle($request->user(), $request->validated(), $key);

        return (new OrderResource($order->load(['account', 'items.service', 'person', 'people.services', 'invoice' => fn ($q) => $q->withSum('payments', 'amount')])))->additional(['meta' => ['request_id' => $request->attributes->get('request_id')]])->response()->setStatusCode(201)->header('Location', url('/api/v1/orders/'.$order->public_id));
    }

    public function show(Request $request, Order $order)
    {
        Gate::authorize('view', $order);

        return (new OrderResource($order->load(['account', 'items.service', 'person', 'people.services', 'invoice' => fn ($q) => $q->withSum('payments', 'amount')])))->additional(['meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }
}
