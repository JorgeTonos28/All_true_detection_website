<?php

namespace App\Services;

use App\Models\IdentitySession;

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
                && collect(['personal_number', 'document_number'])->contains(fn ($key) => is_string($ids[0][$key] ?? null) && preg_replace('/[^0-9]/', '', $ids[0][$key]) === $number);
            if ($approved($ids) && $approved($body['liveness_checks'] ?? null) && $approved($body['face_matches'] ?? null) && $number && $matches) {
                $outcome = 'verified';
                $reason = 'document_liveness_face_match_passed';
            } else {
                $reason = 'missing_checks_or_identity_mismatch';
            }
        } elseif ($body['status'] === 'Declined') {
            $reason = 'provider_declined';
        }

        return ['method' => 'didit', 'outcome' => $outcome, 'reason' => $reason, 'reviewed_at' => now()->toIso8601String()];
    }
}
