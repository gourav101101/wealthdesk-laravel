<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class Format
{
    public static function money(int|float|null $paise): string
    {
        $amount = ($paise ?? 0) / 100;
        $negative = $amount < 0 ? '−' : '';
        $parts = explode('.', number_format(abs($amount), 2, '.', ''));
        $integer = $parts[0];
        if (strlen($integer) > 3) {
            $integer = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($integer, 0, -3)).','.substr($integer, -3);
        }

        return $negative.'₹'.$integer.($parts[1] === '00' ? '' : '.'.$parts[1]);
    }

    public static function short(int|float $paise): string
    {
        if (abs($paise) >= 1000000000) {
            return '₹'.number_format($paise / 1000000000, 2).'Cr';
        }
        if (abs($paise) >= 10000000) {
            return '₹'.number_format($paise / 10000000, 2).'L';
        }

        return static::money($paise);
    }

    public static function date($date, bool $time = false): string
    {
        return $date ? Carbon::parse($date)->timezone('Asia/Kolkata')->format($time ? 'd M Y, h:i a' : 'd M Y') : '—';
    }

    public static function initials(string $name): string
    {
        return collect(explode(' ', $name))->filter()->take(2)->map(fn ($n) => mb_substr($n, 0, 1))->join('');
    }
}
