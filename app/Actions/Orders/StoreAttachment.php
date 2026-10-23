<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderAttachment;
use App\Models\User;
use App\Services\Audit;
use App\Support\PrivateUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class StoreAttachment
{
    public function handle(User $actor, Order $order, UploadedFile $file): OrderAttachment
    {
        Gate::forUser($actor)->authorize('view', $order);
        abort_if($actor->isStaff(), 403, 'Los documentos de entrada deben ser aportados por el cliente.');
        PrivateUpload::validate($file);
        $path = $file->store('attachments/'.$order->public_id, 'local');
        try {
            return DB::transaction(function () use ($actor, $order, $file, $path) {
                $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('view', $order);
                abort_if(in_array($order->status, [OrderStatus::Completed, OrderStatus::Cancelled], true), 409, 'La orden ya está cerrada.');
                abort_if($order->attachments()->count() >= 30, 422, 'Límite de documentos alcanzado.');
                $attachment = $order->attachments()->create(['uploaded_by' => $actor->id, 'file_path' => $path, 'file_name' => basename($file->getClientOriginalName()), 'mime_type' => $file->getMimeType()]);
                Audit::record($actor, 'document.uploaded', $attachment);

                return $attachment;
            }, 3);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }
}
