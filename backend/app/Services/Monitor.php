<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class Monitor
{
    public function __construct(private DestinationPolicy $policy, private DnsResolver $dns, private HttpProbe $http) {}

    public function check(object $service): array
    {
        $start = hrtime(true);
        $ips = [];
        $result = ['available' => false, 'http_status' => null, 'tls_expires_at' => null, 'error' => null];
        try {
            $target = $this->policy->parse($service->url);
            $ips = $this->dns->resolve($target['host']);
            $this->policy->validateAddresses($ips); // All answers must be safe, not only the selected one.
            $result = $this->http->fetch($service->url, $target, $ips[0]);
        } catch (\InvalidArgumentException $e) {
            $result['error'] = 'destination_rejected';
        } catch (\Throwable $e) {
            $result['error'] = 'dns_or_probe_failed';
        }

        return $result + ['target_url' => $service->url, 'response_ms' => (int) round((hrtime(true) - $start) / 1e6), 'dns_addresses' => json_encode($ips), 'checked_at' => now()];
    }

    public function persist(int $serviceId, array $result): void
    {
        DB::transaction(function () use ($serviceId, $result) {
            // Service row serializes checks and incident creation across callers.
            $s = DB::table('services')->where('id', $serviceId)->lockForUpdate()->first();
            if (! $s || ! $s->enabled || $s->url !== $result['target_url']) {
                return;
            }
            DB::table('checks')->insert($result + ['service_id' => $serviceId]);
            if (! $result['available'] && ! DB::table('incidents')->where('service_id', $serviceId)->whereNull('resolved_at')->exists()) {
                DB::table('incidents')->insert(['service_id' => $serviceId, 'status' => 'open', 'opened_at' => now()]);
            }
            // Recovery is observable in checks; resolution deliberately remains manual.
        });
    }
}
