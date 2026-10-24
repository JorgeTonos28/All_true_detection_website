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
use App\Services\UserDirectory;
use App\Support\PersonVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', Rule::enum(OrderStatus::class)], 'q' => ['nullable', 'string', 'max:100'], 'billing' => ['nullable', 'in:pending,paid,unquoted'], 'mine' => ['nullable', 'in:1']]);
        $base = Order::visibleTo($request->user());
        $summary = ['total' => (clone $base)->count(), 'working' => (clone $base)->whereIn('status', ['reviewing', 'quoted', 'in_progress'])->count(), 'pending' => (clone $base)->whereHas('invoice', fn ($q) => $q->where('status', 'unpaid'))->count(), 'completed' => (clone $base)->where('status', 'completed')->count()];
        $query = $base->with(['account', 'invoice' => fn ($q) => $q->withSum('payments', 'amount'), 'assignee', 'items'])->withCount('attachments');
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['q'])) {
            $pattern = UserDirectory::pattern($data['q']);
            $query->where(fn ($q) => $q->whereRaw("LOWER(subject) LIKE ? ESCAPE '!'", [$pattern])->orWhere('public_id', $data['q'])->orWhereRaw("LOWER(external_reference) LIKE ? ESCAPE '!'", [$pattern])->orWhereHas('account', fn ($account) => $account->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])));
        }
        if (($data['billing'] ?? null) === 'unquoted') {
            $query->whereDoesntHave('invoice');
        } elseif (! empty($data['billing'])) {
            $query->whereHas('invoice', fn ($q) => $q->where('status', $data['billing'] === 'paid' ? 'paid' : 'unpaid'));
        }
        if (! empty($data['mine']) && $request->user()->isStaff()) {
            $query->where('assigned_to', $request->user()->id);
        }

        return view('portal.orders.index', ['orders' => $query->latest('id')->paginate(20)->withQueryString(), 'statuses' => OrderStatus::cases(), 'summary' => $summary]);
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
        $order = $action->handle($request->user(), $request->validated(), $request->validated('idempotency_key'), $request->file('documents', []), $request->file('evidence', []), true);
        $request->session()->forget('requested_service');

        return redirect()->route('portal.orders.show', $order)->with('status', 'Solicitud recibida. Nuestro equipo revisará el alcance y preparará su cotización.');
    }

    public function show(Request $request, Order $order)
    {
        Gate::authorize('view', $order);
        $order->load(['items.service', 'person', 'invoice.payments.recorder', 'account', 'requester', 'assignee', 'attachments', 'conversation']);
        $results = ($request->user()->isStaff() || $order->canReleaseResults()) ? $order->results()->when(! $request->user()->isStaff(), fn ($q) => $q->whereNotNull('published_at'))->latest('id')->get() : collect();

        return view('portal.orders.show', ['order' => $order, 'verificationStatus' => PersonVerification::evidenceStatus($order), 'results' => $results, 'staff' => $request->user()->isAdmin() ? User::where('is_active', true)->whereIn('role', ['admin', 'collaborator'])->orderBy('name')->get() : collect()]);
    }

    public function update(Request $request, Order $order, UpdateOrder $action)
    {
        $data = $request->validate(['status' => ['nullable', Rule::enum(OrderStatus::class)], 'assigned_to' => ['nullable', 'integer', 'exists:users,id']]);
        $action->handle($request->user(), $order, $data);

        return back()->with('status', 'Solicitud actualizada.');
    }
}
