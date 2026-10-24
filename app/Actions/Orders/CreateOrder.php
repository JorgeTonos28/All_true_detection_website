<?php

namespace App\Actions\Orders;

use App\Enums\Role;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\Audit;
use App\Services\DocumentBudget;
use App\Services\OrderEvents;
use App\Support\PrivateUpload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrder
{
    public function handle(User $actor, array $data, ?string $key = null, array $files = []): Order
    {
        abort_unless($actor->is_active && $actor->hasVerifiedEmail() && $actor->role === Role::Client && $actor->customer_account_id, 403);
        $staged = PrivateUpload::stage($files);
        $retained = false;
        try {
            [$order, $retained] = DB::transaction(function () use ($actor, $data, $key, $staged) {
                $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                abort_unless($actor->is_active && $actor->hasVerifiedEmail() && $actor->role === Role::Client && $actor->customer_account_id, 403);
                $canonical = ['subject' => $data['subject'], 'details' => $data['details'], 'external_reference' => $data['external_reference'] ?? null, 'consent' => true,
                    'items' => array_map(fn ($item) => ['service_code' => $item['service_code'], 'quantity' => (int) $item['quantity']], $data['items'])];
                usort($canonical['items'], fn ($a, $b) => strcmp($a['service_code'], $b['service_code']));
                if ($staged) {
                    $canonical['documents'] = array_map(fn ($file) => ['sha256' => $file['sha256'], 'name' => $file['file_name'], 'size' => $file['file_size']], $staged);
                }
                $hash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
                if ($key && ($existing = DB::table('idempotency_keys')->where('user_id', $actor->id)->where('key', $key)->first())) {
                    // Preserve retries of keys issued before canonical document fingerprints existed.
                    $legacy = $data;
                    unset($legacy['idempotency_key'], $legacy['service_codes'], $legacy['documents']);
                    usort($legacy['items'], fn ($a, $b) => strcmp($a['service_code'], $b['service_code']));
                    $legacyMatch = ! $staged && hash_equals($existing->request_hash, hash('sha256', json_encode($legacy, JSON_THROW_ON_ERROR)));
                    abort_unless(hash_equals($existing->request_hash, $hash) || $legacyMatch, 409, 'Esta clave ya fue utilizada con otros datos.');

                    return [Order::visibleTo($actor)->findOrFail($existing->order_id), false];
                }
                $services = Service::whereIn('code', array_column($data['items'], 'service_code'))->where('is_active', true)->get()->keyBy('code');
                if ($services->count() !== count($data['items'])) {
                    throw ValidationException::withMessages(['items' => 'Algún servicio no está disponible o está duplicado.']);
                }
                if ($staged) {
                    DocumentBudget::authorize($actor->customer_account_id, array_sum(array_column($staged, 'file_size')));
                }
                $order = Order::create([
                    'customer_account_id' => $actor->customer_account_id, 'requested_by' => $actor->id,
                    'subject' => $data['subject'], 'details' => $data['details'], 'external_reference' => $data['external_reference'] ?? null,
                    'consented_at' => now(),
                ]);
                foreach ($data['items'] as $item) {
                    $service = $services[$item['service_code']];
                    $order->items()->create(['service_id' => $service->id, 'service_name' => $service->name, 'quantity' => $item['quantity']]);
                }
                Conversation::create(['customer_account_id' => $actor->customer_account_id, 'order_id' => $order->id, 'subject' => $order->subject, 'contact_name' => $actor->name, 'contact_email' => $actor->email]);
                foreach ($staged as $file) {
                    $attachment = $order->attachments()->create($file + ['uploaded_by' => $actor->id]);
                    Audit::record($actor, 'document.uploaded', $attachment);
                }
                if ($key) {
                    DB::table('idempotency_keys')->insert(['user_id' => $actor->id, 'key' => $key, 'request_hash' => $hash, 'order_id' => $order->id, 'created_at' => now(), 'updated_at' => now()]);
                }
                Audit::record($actor, 'order.created', $order);
                OrderEvents::record($order, 'order.created');

                return [$order, true];
            }, 3);

            return $order;
        } finally {
            if (! $retained) {
                PrivateUpload::discard($staged);
            }
        }
    }
}
