<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\IpUtils;

final class DestinationPolicy
{
    public function parse(string $url): array
    {
        // No credentials, query secrets, fragments, Unicode ambiguity or alternate URL syntax.
        if (preg_match('/[^\x21-\x7e]|[\\\\]/', $url)) {
            throw new \InvalidArgumentException('Use a plain ASCII HTTP(S) URL.');
        }
        $p = parse_url($url);
        if (! $p || ! isset($p['scheme'],$p['host']) || ! in_array($p['scheme'], ['http', 'https'], true)
         || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment'])) {
            throw new \InvalidArgumentException('Only HTTP(S), without credentials, query or fragment.');
        }
        $host = strtolower($p['host']);
        $port = $p['port'] ?? ($p['scheme'] === 'https' ? 443 : 80);
        if (! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $host)
         || ! in_array($host, config('infrawatch.hosts', []), true)
         || $port !== ($p['scheme'] === 'https' ? 443 : 80)) {
            throw new \InvalidArgumentException('Destination must exactly match an allowed hostname and its standard port.');
        }

        return ['host' => $host, 'port' => $port, 'scheme' => $p['scheme']];
    }

    public function validateAddresses(array $ips): void
    {
        if (! $ips || count($ips) > 32) {
            throw new \InvalidArgumentException('DNS result rejected.');
        }
        foreach ($ips as $ip) {
            if (! is_string($ip) || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \InvalidArgumentException('Non-public destination rejected.');
            }
            $v6 = str_contains($ip, ':');
            // Conservatively reject special-use ranges, transition addresses and IPv4 mapped IPv6.
            if (($v6 && ! IpUtils::checkIp($ip, '2000::/3')) || IpUtils::checkIp($ip, [
                '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
                '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16',
                '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
                '2001::/23', '2001:db8::/32', '2002::/16', '3fff::/20',
            ])) {
                throw new \InvalidArgumentException('Special-use destination rejected.');
            }
        }
    }
}
