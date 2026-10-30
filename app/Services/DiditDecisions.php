<?php

namespace App\Services;

use App\Models\IdentitySession;
use App\Support\IdentityNumber;

class DiditDecisions
{
    public static function summarize(IdentitySession $session, array $body): ?array
    {
        if (! in_array($body['status'], ['Approved', 'Declined', 'In Review', 'Expired', 'Abandoned', 'Kyc Expired'], true)) {
            return null;
        }
        $outcome = $body['status'] === 'Declined' ? 'not_verified' : 'inconclusive';
        $reason = 'provider_review_required';
        if ($body['status'] === 'Approved') {
            // Never turn an empty sandbox approval, missing checks, or a different person's ID into verification.
            $approved = fn ($checks) => is_array($checks) && count($checks) > 0 && collect($checks)->every(fn ($c) => is_array($c) && ($c['status'] ?? null) === 'Approved');
            $ids = is_array($body['id_verifications'] ?? null) ? $body['id_verifications'] : [];
            $number = $session->person->profile['identity_number'] ?? '';
            $matches = count($ids) === 1 && ($ids[0]['issuing_state'] ?? null) === 'DOM'
                && ($ids[0]['verification_method'] ?? null) === 'document'
                && collect(['personal_number', 'document_number'])->contains(fn ($key) => IdentityNumber::matches($ids[0][$key] ?? null, $number));
            $active = is_array($body['liveness_checks'] ?? null) && count($body['liveness_checks']) > 0
                && collect($body['liveness_checks'])->every(fn ($check) => is_array($check) && ($check['method'] ?? null) === 'ACTIVE_3D');
            $recurring = $session->verification_kind === 'recurring';
            $proofAt = ($session->decision['outcome'] ?? null) === 'verified' ? $session->decision['reviewed_at'] : now();
            $reference = $recurring && IdentityReferences::boundToSession($session) && IdentityReferences::validAt($session->subject, $proofAt)
                && empty($body['id_verifications']) && $approved($body['face_matches'] ?? null)
                && collect($body['face_matches'])->every(fn ($check) => ($check['source_image_session_id'] ?? null) === $session->provider_session_id);
            if ($approved($body['liveness_checks'] ?? null) && $active && $approved($body['face_matches'] ?? null)
                && ($recurring ? $reference : ($approved($ids) && $number && $matches))) {
                $outcome = 'verified';
                $reason = $recurring ? 'active_liveness_stored_reference_match_passed' : 'document_liveness_face_match_passed';
            } else {
                $reason = ! $active ? 'active_liveness_required' : ($recurring ? 'identity_reference_or_checks_unavailable' : 'missing_checks_or_identity_mismatch');
            }
        } elseif ($body['status'] === 'Declined') {
            $reason = 'provider_declined';
        }

        return ['method' => 'didit', 'outcome' => $outcome, 'reason' => $reason, 'reviewed_at' => now()->toIso8601String()];
    }
}
