<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\OrderResult;
use App\Models\User;
use App\Services\Audit;
use App\Services\DocumentBudget;
use App\Support\PersonVerification;
use App\Support\PrivateUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class StoreResult
{
    public function handle(User $actor, Order $order, string $summary, ?UploadedFile $file = null, array $findings = []): OrderResult
    {
        Gate::forUser($actor)->authorize('update', $order);
        $staged = $file ? PrivateUpload::stage([$file]) : [];
        $metadata = $staged[0] ?? [];
        try {
            return DB::transaction(function () use ($actor, $order, $summary, $metadata, $findings) {
                $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('update', $order);
                $verificationFindings = PersonVerification::findings($order, $findings);
                if ($metadata) {
                    DocumentBudget::authorize($order->customer_account_id, $metadata['file_size'], $order);
                }
                abort_if($order->results()->count() >= 30, 422, 'Límite de entregas alcanzado.');
                $result = $order->results()->create($metadata + ['created_by' => $actor->id, 'summary' => $summary, 'verification_findings' => $verificationFindings]);
                Audit::record($actor, 'result.prepared', $result);

                return $result;
            });
        } catch (\Throwable $e) {
            PrivateUpload::discard($staged);
            throw $e;
        }
    }
}
