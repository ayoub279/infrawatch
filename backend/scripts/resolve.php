<?php

// Only invoked with a policy-validated hostname, no shell interpolation.
function addresses(string $host, int $depth = 0): array
{
    if ($depth > 5) {
        return [];
    }
    $records = dns_get_record($host, DNS_A | DNS_AAAA | DNS_CNAME);
    if ($records === false) {
        return [];
    }
    $ips = [];
    foreach ($records as $r) {
        if (isset($r['ip'])) {
            $ips[] = $r['ip'];
        }
        if (isset($r['ipv6'])) {
            $ips[] = $r['ipv6'];
        }
        if (($r['type'] ?? '') === 'CNAME') {
            $ips = array_merge($ips, addresses($r['target'], $depth + 1));
        }
    }

    return array_values(array_unique($ips));
}
echo json_encode(addresses($argv[1]), JSON_THROW_ON_ERROR);
