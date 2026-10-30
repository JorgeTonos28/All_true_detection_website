<?php

namespace App\Services;

use App\Models\IdentitySession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class DiditClient
{
    public const STATUSES = ['Not Started', 'In Progress', 'Approved', 'Declined', 'In Review', 'Awaiting User', 'Resubmitted', 'Expired', 'Abandoned', 'Kyc Expired'];

    private function request(string $method, string $path, array $data = []): array
    {
        try {
            // Fixed origin; no redirects, automatic POST retries or provider exception bodies in logs.
            $response = Http::baseUrl('https://verification.didit.me/v3')->withHeaders(['x-api-key' => config('identity.didit.api_key')])
                ->acceptJson()->asJson()->connectTimeout(3)->timeout(12)->withoutRedirecting()->send($method, $path, $data ? ['json' => $data] : []);
            if (strlen($response->body()) > 2097152 || ! is_array($body = $response->json())) {
                throw new DiditRequestFailure($response->status(), 'invalid_response');
            }
            if (! $response->successful()) {
                $status = $response->status();
                $insufficient = in_array('insufficient_credits', [$body['code'] ?? null, data_get($body, 'error.code'), $body['error'] ?? null], true)
                    || in_array($body['detail'] ?? null, ["You don't have enough credits to perform this request", "You don't have enough credits to perform this request."], true);
                $reason = $insufficient || $status === 402 ? 'insufficient_credits' : match ($status) {
                    400, 422 => 'invalid_request', 401 => 'authentication_failed', 403 => 'permission_denied', 404 => 'not_found',
                    429 => 'rate_limited', default => 'provider_unavailable',
                };
                throw new DiditRequestFailure($status, $reason, $method === 'POST' && in_array($status, [400, 401, 402, 403, 404, 422], true) && ! isset($body['session_id']));
            }

            return $body;
        } catch (DiditRequestFailure $error) {
            throw $error;
        } catch (\Throwable) {
            throw new DiditRequestFailure(null, 'transport_error');
        }
    }

    public function create(IdentitySession $session): array
    {
        $profile = $session->person->profile;
        $payload = [
            'workflow_id' => $session->workflow_id, 'vendor_data' => $session->providerReference(),
            'metadata' => ['atd_session_id' => $session->public_id],
            'language' => 'es',
        ];
        // A phone cannot return to the developer's loopback address. Local polling
        // already confirms the result server-to-server; keep Didit's completion screen.
        if (! IdentitySettings::localPolling()) {
            $payload['callback'] = route('identity.return');
        }
        // The provider rejects this field on live keys, before creating a paid/live session.
        if ($session->environment === 'sandbox') {
            $scenario = config('identity.didit.sandbox_scenario', 'approve');
            if (! in_array($scenario, ['approve', 'decline_document_expired', 'decline_face_match_low_similarity', 'review_face_match_borderline'], true)) {
                throw new RuntimeException('Escenario Sandbox no permitido.');
            }
            $payload['sandbox_scenario'] = $scenario;
        }
        if ($session->verification_kind !== 'recurring') {
            $payload['expected_details'] = array_filter(['first_name' => $profile['first_name'], 'last_name' => $profile['last_name'], 'date_of_birth' => $profile['birth_date'] ?? null, 'id_country' => 'DOM', 'expected_document_types' => ['ID']], fn ($v) => $v !== null);
        }
        $body = $this->request('POST', '/session/', $payload);
        if (! Str::isUuid($body['session_id'] ?? '') || ($body['workflow_id'] ?? null) !== $session->workflow_id || ($body['vendor_data'] ?? null) !== $session->providerReference() || ! self::safeUrl($body['url'] ?? null)
            || ($session->verification_kind === 'recurring' && data_get($body, 'metadata.atd_session_id') !== $session->public_id)) {
            throw new DiditRequestFailure(null, 'invalid_creation_response');
        }

        return $body;
    }

    /** Reconcile a lost create response by exact server-owned reference, without a POST. */
    public function findCreatedSession(IdentitySession $session): ?array
    {
        $list = $this->request('GET', '/sessions/?'.http_build_query(['vendor_data' => $session->providerReference(), 'workflow_id' => $session->workflow_id, 'limit' => 2]));
        if (($list['count'] ?? null) === 0 && ($list['results'] ?? null) === [] && empty($list['next'])) {
            return null;
        }
        if (($list['count'] ?? null) !== 1 || ! is_array($list['results'] ?? null) || count($list['results']) !== 1 || ! empty($list['next'])) {
            throw new RuntimeException('La creación requiere conciliación operativa.');
        }
        $candidate = clone $session;
        $id = $list['results'][0]['session_id'] ?? null;
        if (! is_string($id) || ! Str::isUuid($id)) {
            throw new RuntimeException('Referencia remota no válida.');
        }
        $candidate->provider_session_id = $id;
        $body = $this->decision($candidate);
        $url = $body['url'] ?? $body['session_url'] ?? null;
        if (data_get($body, 'metadata.atd_session_id') !== $session->public_id || ! self::safeUrl($url)) {
            throw new RuntimeException('La creación encontrada no corresponde al intento local.');
        }

        return ['session_id' => $id, 'url' => $url];
    }

    public function assertActiveWorkflow(IdentitySession $session): void
    {
        $workflow = $this->request('GET', '/workflows/'.$session->workflow_id.'/');
        $features = array_map('trim', explode('+', (string) ($workflow['features'] ?? '')));
        $liveness = collect(data_get($workflow, 'workflow_graph.nodes', []))->filter(fn ($node) => is_array($node) && ($node['feature'] ?? null) === 'LIVENESS');
        $active = $liveness->isNotEmpty()
            ? $liveness->every(fn ($node) => data_get($node, 'config.face_liveness_method') === 'ACTIVE_3D')
            : empty($workflow['workflow_graph']) && ($workflow['face_liveness_method'] ?? null) === 'ACTIVE_3D' && ($workflow['is_liveness_enabled'] ?? false);
        $hasDocument = (bool) array_intersect(['OCR', 'ID_VERIFICATION'], $features);
        $kindMatches = $session->verification_kind === 'recurring'
            ? ! $hasDocument && (! empty($workflow['workflow_graph']) || ($workflow['workflow_type'] ?? null) === 'biometric_authentication')
            : $hasDocument;
        if (IdentitySettings::localLiveTesting()) {
            $allowed = $session->verification_kind === 'recurring' ? ['LIVENESS', 'FACE_MATCH'] : ['OCR', 'ID_VERIFICATION', 'LIVENESS', 'FACE_MATCH'];
            if (array_diff($features, $allowed) || $liveness->contains(fn ($node) => (bool) data_get($node, 'config.face_search_enabled', false))) {
                throw new RuntimeException('El piloto Live solo admite documento, vida activa y comparación facial 1:1.');
            }
        }
        if (($workflow['workflow_id'] ?? null) !== $session->workflow_id || ($workflow['status'] ?? null) !== 'published' || ($workflow['is_archived'] ?? true)
            || ! $active || ! in_array('LIVENESS', $features, true) || ! in_array('FACE_MATCH', $features, true) || ! $kindMatches) {
            throw new RuntimeException('Se requiere un workflow publicado con documento, prueba de vida ACTIVE_3D y comparación facial.');
        }
    }

    public function decision(IdentitySession $session): array
    {
        $body = $this->request('GET', '/session/'.$session->provider_session_id.'/decision/');
        if (! $this->matchesBinding($session, $body) || ($body['environment'] ?? null) !== $session->environment) {
            throw new RuntimeException('La decisión no corresponde a la sesión de identidad.');
        }

        return $body;
    }

    /** Operational metadata only. This never exports a decision, image or capture URL. */
    public function bindingStatus(IdentitySession $session): array
    {
        $body = $this->request('GET', '/session/'.$session->provider_session_id.'/decision/');
        $bound = $this->matchesBinding($session, $body);
        $environment = in_array($body['environment'] ?? null, ['sandbox', 'live'], true) ? $body['environment'] : null;

        return ['ok' => $bound && $environment === $session->environment, 'binding_matches' => $bound,
            'expected_environment' => $session->environment, 'environment' => $bound ? $environment : null,
            'status' => $bound ? $body['status'] : null, 'application_id' => $session->application_id];
    }

    private function matchesBinding(IdentitySession $session, array $body): bool
    {
        return ($body['session_id'] ?? null) === $session->provider_session_id
            && ($body['workflow_id'] ?? null) === $session->workflow_id
            && ($body['vendor_data'] ?? null) === $session->providerReference()
            && in_array($body['status'] ?? null, self::STATUSES, true)
            && ($session->verification_kind !== 'recurring' || data_get($body, 'metadata.atd_session_id') === $session->public_id);
    }

    public static function safeUrl(mixed $url): bool
    {
        return is_string($url) && strlen($url) <= 2000 && filter_var($url, FILTER_VALIDATE_URL)
            && ($parts = parse_url($url)) && ($parts['scheme'] ?? null) === 'https' && ($parts['host'] ?? null) === 'verify.didit.me'
            && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['port']);
    }
}
