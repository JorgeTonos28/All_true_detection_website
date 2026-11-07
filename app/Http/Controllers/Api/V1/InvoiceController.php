<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['sometimes', 'in:unpaid,paid,overdue'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'order_id' => ['sometimes', 'string', 'size:26']]);
        $query = Invoice::whereHas('order', fn ($orders) => $orders->visibleTo($request->user()))->with('order.items')->withSum('payments', 'amount');
        if (isset($data['status'])) {
            $query->where('status', $data['status'] === 'paid' ? 'paid' : 'unpaid');
            if ($data['status'] === 'overdue') {
                $query->whereDate('due_date', '<', today());
            }
        }
        if (isset($data['order_id'])) {
            $query->whereHas('order', fn ($q) => $q->where('public_id', $data['order_id']));
        }

        return InvoiceResource::collection($query->latest('id')->paginate($data['per_page'] ?? 25));
    }

    public function show(Invoice $invoice)
    {
        Gate::authorize('view', $invoice->order);

        return new InvoiceResource($invoice->load(['order.items', 'payments']));
    }

    public function document(Invoice $invoice)
    {
        Gate::authorize('view', $invoice->order);
        Audit::record(request()->user(), 'invoice.downloaded', $invoice);

        return response()->view('exports.invoice', ['invoice' => $invoice->load(['order.items', 'payments'])])
            ->header('Content-Type', 'text/html; charset=UTF-8')->header('Content-Disposition', 'attachment; filename="'.$invoice->number.'.html"')->header('Cache-Control', 'no-store, private');
    }
}
