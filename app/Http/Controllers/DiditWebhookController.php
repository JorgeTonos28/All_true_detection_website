<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDiditSession;
use App\Models\IdentitySession;
use App\Services\DiditSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DiditWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $body = DiditSignature::verify($request);
        Validator::make($body, ['event_id' => 'required|uuid', 'session_id' => 'required|uuid', 'application_id' => 'required|uuid', 'workflow_id' => 'required|uuid', 'environment' => 'required|in:sandbox,live', 'vendor_data' => 'required|string|max:100', 'webhook_type' => 'required|in:status.updated,data.updated'])->validate();
        $session = DB::transaction(function () use ($body) {
            $session = IdentitySession::where('public_id', $body['vendor_data'])->lockForUpdate()->first();
            // Unknown sessions and other apps must never be imported just because a webhook is signed.
            abort_unless($session && $session->application_id === $body['application_id'] && $session->workflow_id === $body['workflow_id'] && $session->environment === $body['environment'], 404);
            abort_if($session->provider_session_id && $session->provider_session_id !== $body['session_id'], 409);
            $fingerprint = hash('sha256', implode('|', [$body['session_id'], $body['application_id'], $body['workflow_id'], $body['environment'], $body['vendor_data'], $body['webhook_type']]));
            $existing = DB::table('identity_webhook_receipts')->where('event_id', $body['event_id'])->first();
            if ($existing) {
                abort_unless($existing->identity_session_id === $session->id && hash_equals($existing->fingerprint, $fingerprint), 409);

                return $session;
            }
            DB::table('identity_webhook_receipts')->insert(['event_id' => $body['event_id'], 'identity_session_id' => $session->id, 'fingerprint' => $fingerprint, 'created_at' => now()]);
            $session->update(['provider_session_id' => $body['session_id'], 'sync_requested' => true]);

            // Only the minimal receipt is stored. Decisions and URLs are re-fetched with our server key.
            return $session;
        });
        ProcessDiditSession::dispatch($session->id)->afterCommit();

        return response()->json(['received' => true]);
    }
}
