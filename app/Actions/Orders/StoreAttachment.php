<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderAttachment;
use App\Models\User;
use App\Services\Audit;
use App\Services\DocumentBudget;
use App\Support\PrivateUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class StoreAttachment
{
    public function handle(User $actor, Order $order, UploadedFile $file, ?string $key = null): OrderAttachment
    {
        Gate::forUser($actor)->authorize('view', $order);
        abort_if($actor->isStaff(), 403, 'Los documentos de entrada deben ser aportados por el cliente.');
        abort_if(in_array($order->status, [OrderStatus::Completed, OrderStatus::Cancelled], true), 409, 'La orden ya está cerrada.');
        $staged = PrivateUpload::stage([$file]);
        $metadata = $staged[0];
        $retained = false;
        try {
            $attachment = DB::transaction(function () use ($actor, $order, $metadata, $key) {
                $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('view', $order);
                if ($key && ($existing = $order->attachments()->where('idempotency_key', $key)->first())) {
                    abort_unless(hash_equals($existing->sha256, $metadata['sha256']) && $existing->file_name === $metadata['file_name'], 409, 'Esta clave de documento ya fue utilizada con otros datos.');

                    return $existing;
                }
                abort_if(in_array($order->status, [OrderStatus::Completed, OrderStatus::Cancelled], true), 409, 'La orden ya está cerrada.');
                abort_if($order->attachments()->count() >= 30, 422, 'Límite de documentos alcanzado.');
                DocumentBudget::authorize($order->customer_account_id, $metadata['file_size'], $order);
                $attachment = $order->attachments()->create($metadata + ['uploaded_by' => $actor->id, 'idempotency_key' => $key]);
                Audit::record($actor, 'document.uploaded', $attachment);

                return $attachment;
            }, 3);
            $retained = $attachment->file_path === $metadata['file_path'];

            return $attachment;
        } finally {
            if (! $retained) {
                PrivateUpload::discard($staged);
            }
        }
    }
}
