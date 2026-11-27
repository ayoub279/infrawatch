<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class MonitorHealth extends Command
{
    protected $signature = 'monitor:health';

    protected $description = 'Verify a monitoring pass finished within ten minutes';

    public function handle(): int
    {
        $last = Cache::get('monitor:last_finished');
        if (! $last || now()->diffInSeconds(Carbon::parse($last), true) > 600) {
            return self::FAILURE;
        }
        $this->info('Worker heartbeat current.');

        return self::SUCCESS;
    }
}
