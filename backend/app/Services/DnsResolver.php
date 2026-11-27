<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class DnsResolver
{
    public function resolve(string $host): array
    {
        // OS DNS may block beyond PHP socket timeouts. Isolate it in a killable child process.
        $p = new Process([PHP_BINARY, base_path('scripts/resolve.php'), $host]);
        $p->setTimeout(3);
        $p->mustRun();
        $ips = json_decode($p->getOutput(), true, 16, JSON_THROW_ON_ERROR);
        if (! is_array($ips)) {
            throw new \RuntimeException('DNS failed.');
        }

        return $ips;
    }
}
