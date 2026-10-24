<?php

namespace App\Http\Controllers\Web;

use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\UpdateOrder;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', Rule::enum(OrderStatus::class)], 'q' => ['nullable', 'string', 'max:100']]);
        $query = Order::visibleTo($request->user())->with(['account', 'invoice', 'assignee']);
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['q'])) {
            $query->where(fn ($q) => $q->where('subject', 'like', '%'.$data['q'].'%')->orWhere('public_id', $data['q'])->orWhere('external_reference', $data['q']));
        }

        return view('portal.orders.index', ['orders' => $query->latest('id')->paginate(20)->withQueryString(), 'statuses' => OrderStatus::cases()]);
    }

    public function create(Request $request)
    {
        if ($request->user()->isStaff()) {
            return redirect()->route('portal.orders.index')->with('status', 'Para solicitar un servicio debe utilizar una cuenta de cliente.');
        }
        abort_unless($request->user()->customer_account_id, 403);

        $selected = $request->query('service', $request->session()->get('requested_service'));
        $selected = is_string($selected) && Service::where('code', $selected)->where('is_active', true)->exists() ? $selected : null;

        return view('portal.orders.create', ['services' => Service::where('is_active', true)->orderBy('sort_order')->get(), 'selectedService' => $selected]);
    }

    public function store(StoreOrderRequest $request, CreateOrder $action)
    {
        $order = $action->handle($request->user(), $request->validated(), $request->validated('idempotency_key'), $request->file('documents', []));
        $request->session()->forget('requested_service');

        return redirect()->route('portal.orders.show', $order)->with('status', 'Solicitud recibida. Nuestro equipo revisará el alcance y preparará su cotización.');
    }

    public function show(Request $request, Order $order)
    {
        Gate::authorize('view', $order);
        $order->load(['items.service', 'invoice.payments.recorder', 'account', 'requester', 'assignee', 'attachments', 'conversation']);
        $results = ($request->user()->isStaff() || $order->canReleaseResults()) ? $order->results()->when(! $request->user()->isStaff(), fn ($q) => $q->whereNotNull('published_at'))->latest('id')->get() : collect();

        return view('portal.orders.show', ['order' => $order, 'results' => $results, 'staff' => $request->user()->isAdmin() ? User::where('is_active', true)->whereIn('role', ['admin', 'collaborator'])->orderBy('name')->get() : collect()]);
    }

    public function update(Request $request, Order $order, UpdateOrder $action)
    {
        $data = $request->validate(['status' => ['nullable', Rule::enum(OrderStatus::class)], 'assigned_to' => ['nullable', 'integer', 'exists:users,id']]);
        $action->handle($request->user(), $order, $data);

        return back()->with('status', 'Solicitud actualizada.');
    }
}
