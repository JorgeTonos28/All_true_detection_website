<?php

namespace App\Services;

use Illuminate\Http\Request;
use stdClass;

class DiditSignature
{
    public static function canonical(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $properties = get_object_vars($value);
            ksort($properties, SORT_STRING);
            $object = new stdClass;
            foreach ($properties as $key => $item) {
                $object->{$key} = self::canonical($item);
            }

            return $object;
        }

        return is_array($value) ? array_map(self::canonical(...), $value) : $value;
    }

    public static function verify(Request $request): array
    {
        abort_unless($request->isJson(), 415);
        $raw = $request->getContent();
        abort_if(strlen($raw) > 2097152, 413);
        $secret = config('identity.didit.webhook_secret');
        abort_unless(is_string($secret) && strlen($secret) >= 16, 503);
        try {
            $decoded = json_decode($raw, false, 64, JSON_THROW_ON_ERROR);
            $canonical = json_encode(self::canonical($decoded), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            abort(400);
        }
        $timestamp = $request->header('X-Timestamp');
        // Check BOTH timestamps. Refreshing the unsigned header must not revive a captured body.
        abort_unless($decoded instanceof stdClass && is_numeric($timestamp) && abs(now()->timestamp - (int) $timestamp) <= 300
            && is_int($decoded->timestamp ?? null) && abs(now()->timestamp - $decoded->timestamp) <= 300, 401);
        $expected = hash_hmac('sha256', $canonical, $secret);
        $valid = false;
        foreach (['X-Signature-V2', 'X-Signature-V2-Previous'] as $header) {
            $signature = $request->header($header, '');
            $valid = $valid || (is_string($signature) && preg_match('/^[a-f0-9]{64}$/D', $signature) && hash_equals($expected, $signature));
        }
        abort_unless($valid, 401);

        return json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    }
}
