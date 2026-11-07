<?php

namespace App\Services;

use App\Models\CustomerAccount;
use App\Models\Order;
use App\Models\OrderAttachment;
use App\Models\OrderResult;

class DocumentBudget
{
    // Call inside the same transaction as the insert. Account lock serializes shared-account uploads.
    public static function authorize(int $accountId, int $bytes, ?Order $order = null): void
    {
        CustomerAccount::whereKey($accountId)->lockForUpdate()->firstOrFail();
        $attachments = OrderAttachment::whereHas('order', fn ($q) => $q->where('customer_account_id', $accountId));
        $results = OrderResult::whereNotNull('file_path')->whereHas('order', fn ($q) => $q->where('customer_account_id', $accountId));
        abort_if((clone $attachments)->whereNull('file_size')->exists() || (clone $results)->whereNull('file_size')->exists(), 503, 'El almacenamiento requiere preparar los metadatos de documentos anteriores. Contacte a ATD.');
        $used = (int) $attachments->sum('file_size') + (int) $results->sum('file_size');
        abort_if($used + $bytes > config('portal.document_account_max_bytes'), 422, 'La cuenta alcanzó su límite de almacenamiento de documentos. Contacte a ATD.');
        if ($order) {
            $used = (int) $order->attachments()->sum('file_size') + (int) $order->results()->sum('file_size');
            abort_if($used + $bytes > config('portal.document_order_max_bytes'), 422, 'La solicitud no admite más de 100 MB de documentos.');
        }
    }
}
