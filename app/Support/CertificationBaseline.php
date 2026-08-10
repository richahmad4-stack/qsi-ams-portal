<?php

namespace App\Support;

final class CertificationBaseline
{
    public const CODES = [
        'ISO 9001:2015',
        'ISO 14001:2015',
        'ISO 45001:2018',
        'ISO 22000:2018',
        'HACCP',
    ];

    public static function isApproved(string $code): bool
    {
        return in_array(trim($code), self::CODES, true);
    }
}
