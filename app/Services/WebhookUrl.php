<?php

namespace App\Services;

class WebhookUrl
{
    public function host(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        abort_unless(filter_var($url, FILTER_VALIDATE_URL) && ($parts['scheme'] ?? '') === 'https' && (! isset($parts['port']) || $parts['port'] === 443) && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['fragment']) && preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/D', $host) && ! filter_var($host, FILTER_VALIDATE_IP) && in_array($host, config('portal.webhook_allowed_hosts'), true), 422, 'El destino HTTPS debe estar autorizado por operaciones.');

        return $host;
    }

    public function publicAddress(string $url): array
    {
        $host = $this->host($url);
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = array_values(array_filter(array_map(fn ($r) => $r['ip'] ?? $r['ipv6'] ?? null, $records ?: [])));
        abort_unless(count($addresses) > 0, 422, 'No se pudo resolver el destino.');
        foreach ($addresses as $ip) {
            abort_unless(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) && ! str_starts_with(strtolower($ip), '::ffff:') && ! str_starts_with(strtolower($ip), '64:ff9b:'), 422, 'El destino debe resolver exclusivamente a direcciones públicas.');
        }
        $address = $addresses[0];

        return [$host, str_contains($address, ':') ? '['.$address.']' : $address];
    }
}
