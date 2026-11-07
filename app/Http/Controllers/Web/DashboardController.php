<?php

namespace App\Http\Controllers\Web;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Order;
use App\Services\OrderFilters;
use App\Services\OrderReporting;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $filters = OrderFilters::validate($request);
        $query = OrderFilters::apply(Order::visibleTo($request->user()), $filters, $request->user());
        $filtered = (bool) array_filter($filters, fn ($value) => $value !== null && $value !== '');

        return view('portal.dashboard', [
            'money' => OrderReporting::money($query), 'statuses' => OrderStatus::cases(), 'filtered' => $filtered,
            'counts' => (clone $query)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'orders' => (clone $query)->withIdentityCredit()->with(['account', 'invoice' => fn ($invoice) => $invoice->withSum('payments', 'amount'), 'items', 'assignee'])->latest('id')->limit(8)->get(),
            'conversationCount' => Conversation::visibleTo($request->user())->where('status', 'open')->when($filtered, fn ($conversations) => $conversations->whereIn('order_id', (clone $query)->select('orders.id')))->count(),
        ]);
    }
}
