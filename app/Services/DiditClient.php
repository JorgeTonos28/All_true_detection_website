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
            if (! $response->successful() || strlen($response->body()) > 2097152 || ! is_array($body = $response->json())) {
                throw new RuntimeException;
            }

            return $body;
        } catch (\Throwable) {
            throw new RuntimeException('No se pudo confirmar la operación con el proveedor de identidad.');
        }
    }

    public function create(IdentitySession $session): array
    {
        $profile = $session->person->profile;
        $body = $this->request('POST', '/session/', [
            'workflow_id' => $session->workflow_id, 'vendor_data' => $session->public_id,
            'callback' => route('identity.return'), 'language' => 'es',
            'expected_details' => array_filter(['first_name' => $profile['first_name'], 'last_name' => $profile['last_name'], 'date_of_birth' => $profile['birth_date'] ?? null, 'id_country' => 'DOM', 'expected_document_types' => ['ID']], fn ($v) => $v !== null),
        ]);
        if (! Str::isUuid($body['session_id'] ?? '') || ($body['workflow_id'] ?? null) !== $session->workflow_id || ($body['vendor_data'] ?? null) !== $session->public_id || ! self::safeUrl($body['url'] ?? null)) {
            throw new RuntimeException('Respuesta de creación de identidad no válida.');
        }

        return $body;
    }

    public function assertActiveWorkflow(IdentitySession $session): void
    {
        $workflow = $this->request('GET', '/workflows/'.$session->workflow_id.'/');
        $features = array_map('trim', explode('+', (string) ($workflow['features'] ?? '')));
        $liveness = collect(data_get($workflow, 'workflow_graph.nodes', []))->filter(fn ($node) => is_array($node) && ($node['feature'] ?? null) === 'LIVENESS');
        $active = $liveness->isNotEmpty()
            ? $liveness->every(fn ($node) => data_get($node, 'config.face_liveness_method') === 'ACTIVE_3D')
            : empty($workflow['workflow_graph']) && ($workflow['face_liveness_method'] ?? null) === 'ACTIVE_3D' && ($workflow['is_liveness_enabled'] ?? false);
        if (($workflow['workflow_id'] ?? null) !== $session->workflow_id || ($workflow['status'] ?? null) !== 'published' || ($workflow['is_archived'] ?? true)
            || ! $active || ! in_array('LIVENESS', $features, true) || ! in_array('FACE_MATCH', $features, true) || ! array_intersect(['OCR', 'ID_VERIFICATION'], $features)) {
            throw new RuntimeException('Se requiere un workflow publicado con documento, prueba de vida ACTIVE_3D y comparación facial.');
        }
    }

    public function decision(IdentitySession $session): array
    {
        $body = $this->request('GET', '/session/'.$session->provider_session_id.'/decision/');
        if (($body['session_id'] ?? null) !== $session->provider_session_id || ($body['workflow_id'] ?? null) !== $session->workflow_id || ($body['vendor_data'] ?? null) !== $session->public_id || ($body['environment'] ?? null) !== $session->environment || ! in_array($body['status'] ?? null, self::STATUSES, true)) {
            throw new RuntimeException('La decisión no corresponde a la sesión de identidad.');
        }

        return $body;
    }

    public static function safeUrl(mixed $url): bool
    {
        return is_string($url) && strlen($url) <= 2000 && filter_var($url, FILTER_VALIDATE_URL)
            && ($parts = parse_url($url)) && ($parts['scheme'] ?? null) === 'https' && ($parts['host'] ?? null) === 'verify.didit.me'
            && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['port']);
    }
}
