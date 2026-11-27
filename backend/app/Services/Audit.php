<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class Audit
{
    public static function record(?int $actor, string $action, ?int $id, array $details = []): void
    {
        // Only deliberately selected non-secret fields; never request payloads.
        DB::table('audit_logs')->insert(['actor_id' => $actor, 'action' => $action, 'subject_id' => $id,
            'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
