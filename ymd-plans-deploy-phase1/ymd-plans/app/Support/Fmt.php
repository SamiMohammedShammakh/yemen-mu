<?php

namespace App\Support;

class Fmt
{
    /** رقم بصيغة مقروءة: بلا كسور زائدة */
    public static function num($v, int $dec = 2): string
    {
        if ($v === null || $v === '') {
            return '—';
        }
        $v = round((float) $v, $dec);
        $s = number_format($v, $dec, '.', ',');

        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
    }

    public static function pct($v): string
    {
        return $v === null ? '—' : self::num($v, 1) . '%';
    }

    public static function date($d): string
    {
        return $d ? \Illuminate\Support\Carbon::parse($d)->format('Y-m-d') : '—';
    }

    public static function dt($d): string
    {
        return $d ? \Illuminate\Support\Carbon::parse($d)->timezone(config('app.timezone'))->format('Y-m-d H:i') : '—';
    }
}
