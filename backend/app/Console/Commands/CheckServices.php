<?php

namespace App\Console\Commands;

use App\Services\Monitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class CheckServices extends Command
{
    protected $signature = 'monitor:check';

    protected $description = 'Check up to 50 services sequentially under a shared database lock';

    public function handle(Monitor $monitor): int
    {
        $lock = Cache::lock('monitor:check', 600);
        if (! $lock->get()) {
            $this->info('Check already running.');

            return self::SUCCESS;
        }
        $postgres = DB::getDriverName() === 'pgsql';
        $held = false;
        try {
            // Session-level lock does not expire during a slow database operation.
            if ($postgres) {
                $held = (bool) DB::scalar('SELECT pg_try_advisory_lock(4603001)');
                if (! $held) {
                    $this->info('Another PostgreSQL worker owns the monitoring lock.');

                    return self::SUCCESS;
                }
            }
            foreach (DB::table('services')->where('enabled', true)->orderBy('id')->limit(50)->get() as $service) {
                $monitor->persist($service->id, $monitor->check($service));
            }
            Cache::put('monitor:last_finished', now()->toIso8601String(), 86400);
            $this->info('Monitoring pass completed.');
        } finally {
            if ($held) {
                DB::select('SELECT pg_advisory_unlock(4603001)');
            }
            $lock->release();
        }

        return self::SUCCESS;
    }
}
