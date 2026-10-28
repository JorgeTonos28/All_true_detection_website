<?php

namespace App\Services;

use App\Models\IdentitySession;
use Illuminate\Support\Facades\DB;

class ProcessIdentitySession
{
    public function handle(int $id): void
    {
        $session = IdentitySession::find($id);
        if (! $session || ! IdentitySettings::ready() || $session->application_id !== config('identity.didit.application_id') || $session->environment !== config('identity.didit.environment') || $session->order->status->value === 'cancelled') {
            return;
        }
        if ($session->state === 'pending') {
            if ($session->access_channel === 'api_credit' && ! $session->order->account->hasIdentityApiCredit()) {
                return;
            }
            $claimed = IdentitySession::whereKey($id)->where('state', 'pending')->update(['state' => 'creating', 'updated_at' => now()]);
            if (! $claimed) {
                return;
            }
            try {
                app(DiditClient::class)->assertActiveWorkflow($session);
            } catch (\Throwable) {
                // No session POST has happened: pause safely instead of running another liveness method.
                IdentitySession::whereKey($id)->update(['state' => 'needs_attention', 'updated_at' => now()]);

                return;
            }
            try {
                $body = app(DiditClient::class)->create($session);
                DB::transaction(function () use ($id, $body) {
                    $session = IdentitySession::whereKey($id)->lockForUpdate()->firstOrFail();
                    if ($session->provider_session_id && $session->provider_session_id !== $body['session_id']) {
                        $session->update(['state' => 'create_uncertain']);

                        return;
                    }
                    $session->update(['provider_session_id' => $body['session_id'], 'verification_url' => $body['url'], 'state' => 'awaiting_person', 'sync_requested' => true,
                        'credit_accepted_at' => $session->access_channel === 'api_credit' ? ($session->credit_accepted_at ?? now()) : null]);
                    Audit::record(null, 'identity.session_created', $session);
                });
            } catch (\Throwable) {
                // The provider might have accepted a timed-out POST. Do not risk duplicate billing.
                IdentitySession::whereKey($id)->whereNull('provider_session_id')->update(['state' => 'create_uncertain', 'updated_at' => now()]);

                return;
            }
        }
        DB::transaction(function () use ($id) {
            // Serialize reads so an older HTTP response cannot overwrite a newer decision.
            $session = IdentitySession::whereKey($id)->lockForUpdate()->firstOrFail();
            if (! $session->provider_session_id) {
                return;
            }
            try {
                $body = app(DiditClient::class)->decision($session);
            } catch (\Throwable) {
                $session->update(['sync_requested' => true]);

                return;
            }
            $decision = DiditDecisions::summarize($session, $body);
            $previous = $session->decision;
            $changed = ($previous['outcome'] ?? null) !== ($decision['outcome'] ?? null) || ($previous['reason'] ?? null) !== ($decision['reason'] ?? null);
            if (! $changed && $decision && $previous) {
                $decision['reviewed_at'] = $previous['reviewed_at'];
            }
            $state = $decision ? 'received' : (in_array($body['status'], ['Not Started', 'Resubmitted'], true) ? 'awaiting_person' : 'processing');
            $url = $body['url'] ?? $body['session_url'] ?? null;
            $session->fill(['provider_status' => $body['status'], 'decision' => $decision, 'state' => $state, 'sync_requested' => false, 'last_synced_at' => now()]);
            if ($session->access_channel === 'api_credit' && ! $session->credit_accepted_at) {
                $session->credit_accepted_at = now();
            }
            if (DiditClient::safeUrl($url)) {
                $session->verification_url = $url;
            }
            $session->save();
            if ($changed) {
                // A later correction/expiry must not leave an obsolete published conclusion accessible.
                foreach ($session->order->results()->whereNotNull('published_at')->get() as $result) {
                    $result->update(['published_at' => null, 'published_by' => null]);
                    Audit::record(null, 'identity.publication_invalidated', $result);
                }
                Audit::record(null, 'identity.decision_received', $session);
                $session->increment('decision_revision');
                IdentityEvents::record($session);
            }
            if ($session->access_channel !== 'api_credit') {
                IdentityChecks::prepare($session->order);
            }
        });
    }
}
