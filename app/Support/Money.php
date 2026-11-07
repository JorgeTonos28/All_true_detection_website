<?php

namespace App\Support;

class Money
{
    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function parse(string $amount): int
    {
        abort_unless(preg_match('/^[0-9]{1,10}(?:\.[0-9]{1,2})?$/D', $amount), 422, 'Importe no válido. Use hasta dos decimales.');
        [$whole,$fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole) * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function format(int $cents, ?string $currency = null): string
    {
        return ($currency ?? config('portal.currency')).' '.number_format(intdiv($cents, 100), 0, '.', ',').'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
