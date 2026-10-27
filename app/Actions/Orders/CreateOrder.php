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
use App\Support\PersonVerification;
use App\Support\PrivateUpload;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrder
{
    public function handle(User $actor, array $data, ?string $key = null, array $files = [], array $evidence = [], bool $requireEvidence = false, array $personEvidence = []): Order
    {
        abort_unless($actor->is_active && $actor->hasVerifiedEmail() && $actor->role === Role::Client && $actor->customer_account_id, 403);
        $profile = isset($data['person']) ? PersonVerification::profile($data) : null;
        $people = PersonVerification::people($data);
        if ($people && $evidence) {
            throw ValidationException::withMessages(['evidence' => 'Con varias fichas use evidencia asociada a cada persona, no evidencia general sin destinatario.']);
        }
        foreach ($evidence as $purpose => $file) {
            PersonVerification::validateEvidence(PersonVerification::selected($data['items']), $purpose, $file);
        }
        $purposes = array_merge(array_fill(0, count($files), 'supporting'), array_keys($evidence));
        $uploadFiles = array_merge($files, array_values($evidence));
        $personIndexes = array_fill(0, count($uploadFiles), null);
        foreach ($personEvidence as $index => $documents) {
            if (! isset($people[$index]) || ! is_array($documents)) {
                throw ValidationException::withMessages(['person_evidence' => 'La evidencia debe corresponder a una persona de esta solicitud.']);
            }
            foreach ($documents as $purpose => $file) {
                PersonVerification::validateEvidence($people[$index]['service_codes'], $purpose, $file);
                $uploadFiles[] = $file;
                $purposes[] = $purpose;
                $personIndexes[] = $index;
            }
        }
        $staged = PrivateUpload::stage($uploadFiles, config('portal.document_initial_max_files', 20));
        foreach ($staged as $index => &$metadata) {
            $metadata['purpose'] = $purposes[$index];
            if ($personIndexes[$index] !== null) {
                $metadata['_person_index'] = $personIndexes[$index];
            }
        }
        unset($metadata);
        $retained = false;
        try {
            [$order, $retained] = DB::transaction(function () use ($actor, $data, $key, $staged, $profile, $people, $requireEvidence) {
                $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                abort_unless($actor->is_active && $actor->hasVerifiedEmail() && $actor->role === Role::Client && $actor->customer_account_id, 403);
                $canonical = ['subject' => $data['subject'], 'details' => $data['details'] ?? null, 'external_reference' => $data['external_reference'] ?? null, 'consent' => true,
                    'items' => array_map(fn ($item) => ['service_code' => $item['service_code'], 'quantity' => (int) $item['quantity']], $data['items'])];
                usort($canonical['items'], fn ($a, $b) => strcmp($a['service_code'], $b['service_code']));
                if ($profile) {
                    $canonical['person'] = Arr::sortRecursive($profile);
                }
                if ($people) {
                    // Preserve person positions: API consumers associate returned IDs with this order.
                    $canonical['people'] = array_map(fn ($person) => Arr::sortRecursive($person), $people);
                }
                if ($staged) {
                    $canonical['documents'] = array_map(fn ($file) => ['sha256' => $file['sha256'], 'name' => $file['file_name'], 'size' => $file['file_size']] + ($file['purpose'] !== 'supporting' ? ['purpose' => $file['purpose']] : []) + (isset($file['_person_index']) ? ['person_index' => $file['_person_index']] : []), $staged);
                }
                $encoded = json_encode($canonical, JSON_THROW_ON_ERROR);
                // A plain digest of personal fields could enable offline guessing after a DB leak.
                $fingerprints = ($profile || $people)
                    ? array_map(fn ($secret) => hash_hmac('sha256', "atd:person-order:v1\0".$encoded, $secret), array_values(array_filter(array_merge([config('app.key')], config('app.previous_keys', [])))))
                    : [hash('sha256', $encoded)];
                abort_unless($fingerprints, 503);
                $hash = $fingerprints[0];
                if ($key && ($existing = DB::table('idempotency_keys')->where('user_id', $actor->id)->where('key', $key)->first())) {
                    // Preserve retries of keys issued before canonical document fingerprints existed.
                    $legacy = $data;
                    unset($legacy['idempotency_key'], $legacy['service_codes'], $legacy['documents'], $legacy['evidence'], $legacy['person_evidence']);
                    usort($legacy['items'], fn ($a, $b) => strcmp($a['service_code'], $b['service_code']));
                    $legacyMatch = ! $profile && ! $people && ! $staged && hash_equals($existing->request_hash, hash('sha256', json_encode($legacy, JSON_THROW_ON_ERROR)));
                    $matches = collect($fingerprints)->contains(fn ($candidate) => hash_equals($existing->request_hash, $candidate));
                    abort_unless($matches || $legacyMatch, 409, 'Esta clave ya fue utilizada con otros datos.');

                    return [Order::visibleTo($actor)->findOrFail($existing->order_id), false];
                }
                // A matching historical retry may return its old order; new cases MUST have a valid person.
                if (! $people) {
                    $profile ??= PersonVerification::profile($data);
                }
                PersonVerification::distinctDocuments($staged);
                $services = Service::whereIn('code', array_column($data['items'], 'service_code'))->where('is_active', true)->get()->keyBy('code');
                if ($services->count() !== count($data['items'])) {
                    throw ValidationException::withMessages(['items' => 'Algún servicio no está disponible o está duplicado.']);
                }
                if ($staged) {
                    DocumentBudget::authorize($actor->customer_account_id, array_sum(array_column($staged, 'file_size')));
                }
                $order = Order::create([
                    'customer_account_id' => $actor->customer_account_id, 'requested_by' => $actor->id,
                    'subject' => $data['subject'], 'details' => $data['details'] ?? null, 'external_reference' => $data['external_reference'] ?? null,
                    'consented_at' => now(),
                ]);
                foreach ($data['items'] as $item) {
                    $service = $services[$item['service_code']];
                    $order->items()->create(['service_id' => $service->id, 'service_name' => $service->name, 'quantity' => $item['quantity']]);
                }
                $records = [];
                foreach ($people as $index => $person) {
                    $codes = $person['service_codes'];
                    unset($person['service_codes']);
                    $records[$index] = $order->people()->create(['profile' => $person]);
                    $records[$index]->services()->attach(array_map(fn ($code) => $services[$code]->id, $codes));
                }
                if ($profile) {
                    $records[0] = $order->people()->create(['profile' => $profile]);
                    $records[0]->services()->attach(array_map(fn ($code) => $services[$code]->id, PersonVerification::selected($data['items'])));
                }
                Conversation::create(['customer_account_id' => $actor->customer_account_id, 'order_id' => $order->id, 'subject' => $order->subject, 'contact_name' => $actor->name, 'contact_email' => $actor->email]);
                foreach ($staged as $file) {
                    $index = $file['_person_index'] ?? (count($records) === 1 && $file['purpose'] !== 'supporting' ? 0 : null);
                    unset($file['_person_index']);
                    $file['order_person_id'] = $index !== null ? $records[$index]->id : null;
                    $attachment = $order->attachments()->create($file + ['uploaded_by' => $actor->id]);
                    Audit::record($actor, 'document.uploaded', $attachment);
                }
                if ($requireEvidence) {
                    PersonVerification::requireEvidence($order);
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
