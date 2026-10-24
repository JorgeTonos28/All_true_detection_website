<?php

namespace App\Http\Controllers\Web;

use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function issue(Request $request, Order $order, IssueInvoice $action)
    {
        Gate::authorize('administer', $order);
        $data = $request->validate(['prices' => ['required', 'array'], 'prices.*' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000000'], 'tax_percent' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'], 'due_date' => ['nullable', 'date', 'after_or_equal:today']]);
        $data['prices'] = array_map(fn ($price) => Money::parse((string) $price), $data['prices']);
        $data['tax_basis_points'] = Money::parse((string) $data['tax_percent']);
        $action->handle($request->user(), $order, $data);

        return back()->with('status', 'Factura emitida. Los importes quedaron fijados.');
    }

    public function pay(Request $request, Invoice $invoice, RecordPayment $action)
    {
        Gate::authorize('administer', $invoice->order);
        $data = $request->validate(['amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:2000000000'], 'method' => ['required', Rule::in(['bank_transfer', 'cash', 'check', 'other'])], 'reference' => ['required', 'string', 'max:100'], 'received_at' => ['nullable', 'date', 'before_or_equal:now']]);
        $data['amount'] = Money::parse((string) $data['amount']);
        $action->handle($request->user(), $invoice, $data);

        return back()->with('status', 'Pago registrado y saldo actualizado.');
    }

    public function show(Invoice $invoice)
    {
        Gate::authorize('view', $invoice->order);

        return view('portal.invoice', ['invoice' => $invoice->load(['order.items', 'payments'])]);
    }
}
