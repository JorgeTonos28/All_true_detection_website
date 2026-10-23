<?php
namespace App\Support;
class Money
{
    public static function format(int $cents, ?string $currency = null): string
    {
        return ($currency ?? config('portal.currency')).' '.number_format(intdiv($cents,100),0,'.',',').'.'.str_pad((string) ($cents % 100),2,'0',STR_PAD_LEFT);
    }
}
