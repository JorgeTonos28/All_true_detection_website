<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

class OrderReporting
{
    public static function money(Builder $orders): array
    {
        $ids = (clone $orders)->select('orders.id');
        $invoiced = Invoice::whereIn('order_id', clone $ids)->selectRaw('currency, SUM(total) AS amount')->groupBy('currency')->pluck('amount', 'currency');
        $paid = Payment::join('invoices', 'payments.invoice_id', '=', 'invoices.id')->whereIn('invoices.order_id', clone $ids)
            ->selectRaw('invoices.currency, SUM(payments.amount) AS amount')->groupBy('invoices.currency')->pluck('amount', 'currency');
        $result = [];
        foreach ($invoiced as $currency => $total) {
            $collected = (int) ($paid[$currency] ?? 0);
            $result[$currency] = ['collected' => $collected, 'outstanding' => (int) $total - $collected];
        }

        return $result ?: [config('portal.currency') => ['collected' => 0, 'outstanding' => 0]];
    }
}
