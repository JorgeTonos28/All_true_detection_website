<?php

namespace App\Actions\Identity;

use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\RequestIdentitySession;
use App\Models\IdentitySubject;
use App\Models\Order;
use App\Models\User;
use App\Services\IdentityPartnerAccess;
use App\Services\IdentityReferences;
use App\Services\IdentitySettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateRecurringOrder
{
    public function handle(User $actor, IdentitySubject $subject, array $data, string $key, string $channel = 'web'): Order
    {
        abort_unless(! $actor->isStaff() && $actor->customer_account_id === $subject->customer_account_id, 403);
        abort_unless(in_array($channel, ['web', 'api_credit'], true), 422);
        if ($channel === 'api_credit') {
            IdentityPartnerAccess::authorize($actor, 'orders:write');
            IdentityPartnerAccess::authorize($actor, 'identity:verify');
        }
        $data = Validator::make($data, ['consent' => 'required|accepted', 'notice_version' => 'required|in:'.config('identity.reuse_notice_version'),
            'subject' => 'nullable|string|max:200', 'details' => 'nullable|string|max:10000', 'external_reference' => 'nullable|string|max:100',
            'portrait_image' => 'prohibited', 'reference_image' => 'prohibited', 'identity_number' => 'prohibited', 'workflow_id' => 'prohibited', 'decision' => 'prohibited'])->validate();
        Validator::make(['key' => $key], ['key' => ['required', 'string', 'min:8', 'max:128', 'regex:/^[A-Za-z0-9._:-]+$/D']])->validate();
        abort_unless(IdentitySettings::mode() === 'didit' && IdentitySettings::reuseReady(), 503);
        abort_unless(IdentityReferences::usable($subject), 409, 'La referencia no está disponible; requiere una nueva verificación completa.');
        IdentityReferences::refreshSource($subject);

        return DB::transaction(function () use ($actor, $subject, $data, $key, $channel) {
            $order = app(CreateOrder::class)->handle($actor, ['subject' => $data['subject'] ?? 'Reverificación de identidad', 'details' => $data['details'] ?? null,
                'external_reference' => $data['external_reference'] ?? null, 'consent' => true,
                'items' => [['service_code' => 'identity-verification', 'quantity' => 1]],
                'people' => [$subject->profile + ['authorization_confirmed' => true, 'service_codes' => ['identity-verification']]]], $key, identitySubject: $subject);
            if ($channel === 'api_credit') {
                app(RequestIdentitySession::class)->handle($actor, $order, $order->people()->firstOrFail(), $data, $channel);
            }

            return $order;
        }, 3);
    }
}
