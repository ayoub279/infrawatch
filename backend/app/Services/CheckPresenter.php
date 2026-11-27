<?php

namespace App\Services;

use Carbon\Carbon;

final class CheckPresenter
{
    public static function present(?object $check): ?object
    {
        if (! $check) {
            return null;
        }
        $check->available = (bool) $check->available;
        $check->checked_at = Carbon::parse($check->checked_at)->utc()->toISOString();
        if ($check->tls_expires_at) {
            $check->tls_expires_at = Carbon::parse($check->tls_expires_at)->utc()->toISOString();
        }
        $check->dns_addresses = json_decode($check->dns_addresses, true);

        return $check;
    }
}
