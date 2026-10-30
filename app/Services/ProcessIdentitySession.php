<?php

namespace App\Services;

use App\Models\CustomerAccount;
use App\Models\IdentitySession;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class ProcessIdentitySession
{
    public function handle(int $id): void
    {
        $session = IdentitySession::find($id);
        if (! $session || ! IdentitySettings::ready() || $session->application_id !== config('identity.didit.application_id') || $session->environment !== config('identity.didit.environment') || ($session->order->status->value === 'cancelled' && ! $session->provider_session_id)) {
            return;
        }
        if ($session->state === 'pending') {
            if ($session->access_channel === 'web' && ! $session->order->hasConfirmedPayment()) {
                return;
            }
            if ($session->access_channel === 'api_credit' && ! $session->order->account->hasIdentityApiCredit()) {
                return;
            }
            if ($session->verification_kind === 'recurring') {
                if (! IdentitySettings::reuseReady() || ! $session->subject || ! IdentityReferences::usable($session->subject)) {
                    $session->update(['state' => 'needs_attention']);

                    return;
                }
                try {
                    IdentityReferences::refreshSource($session->subject);
                } catch (\Throwable) {
                    return; // No creation POST yet: reconciliation can retry a reference read safely.
                }
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
            if ($session->access_channel === 'api_credit' && ! CustomerAccount::find($session->order->customer_account_id)?->hasIdentityApiCredit()) {
                // Preflight made no session POST; a suspended pending reservation can safely wait.
                IdentitySession::whereKey($id)->update(['state' => 'pending', 'updated_at' => now()]);

                return;
            }
            if (Order::whereKey($session->order_id)->where('status', 'cancelled')->exists()) {
                IdentitySession::whereKey($id)->whereNull('provider_session_id')->update(['state' => 'cancelled_before_capture', 'provider_completed_at' => now(), 'updated_at' => now()]);

                return;
            }
            if ($session->verification_kind === 'recurring') {
                $session->refresh();
                if (! IdentityReferences::boundToSession($session) || ! IdentityReferences::usable($session->subject)) {
                    $session->update(['state' => 'needs_attention', 'provider_completed_at' => now()]);

                    return; // Reference withdrawn/expired during preflight: no creation POST was sent.
                }
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
        DB::transaction(function () use ($id, $session) {
            // Lock the parent first: under MySQL REPEATABLE READ, two people must not each
            // finalize against an older snapshot of the other's decision and leave the order stuck.
            $order = Order::whereKey($session->order_id)->lockForUpdate()->firstOrFail();
            $session = IdentitySession::whereKey($id)->lockForUpdate()->firstOrFail();
            if (! $session->provider_session_id) {
                return;
            }
            if ($session->verification_kind === 'recurring' && $session->subject?->sourceSession?->sync_requested) {
                // Hold the previous dated conclusion while the source update is unknown;
                // a transport outage must not rewrite it as a new verification attempt.
                $session->update(['sync_requested' => true]);

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
            $session->provider_completed_at = in_array($body['status'], ['Approved', 'Declined', 'Expired', 'Abandoned', 'Kyc Expired'], true)
                ? ($session->provider_completed_at ?? now()) : null;
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
                if ($session->verification_kind === 'initial' && ($subject = $session->enrolledSubject()->first())) {
                    // GET blocks obsolete dependent conclusions immediately; the scheduler also
                    // refreshes their revisions and sends metadata-only correction events.
                    $subject->sessions()->whereNotNull('provider_session_id')->update(['sync_requested' => true]);
                }
            }
            if ($session->access_channel !== 'api_credit') {
                IdentityChecks::prepare($session->order);
            }
        }, 3);
    }
}
