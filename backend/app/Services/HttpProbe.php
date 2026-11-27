<?php

namespace App\Services;

class HttpProbe
{
    public function fetch(string $url, array $target, string $ip): array
    {
        $curl = curl_init($url);
        $bytes = 0;
        $headers = 0;
        $oversize = false;
        $pin = str_contains($ip, ':') ? '['.$ip.']' : $ip;
        curl_setopt_array($curl, [
            CURLOPT_PROXY => '', CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
            CURLOPT_CONNECTTIMEOUT_MS => 2000, CURLOPT_TIMEOUT_MS => 5000,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RESOLVE => [$target['host'].':'.$target['port'].':'.$pin],
            CURLOPT_CERTINFO => true, CURLOPT_USERAGENT => 'InfraWatch/1.0',
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$bytes, &$oversize) {
                $bytes += strlen($chunk);
                if ($bytes > 1048576) {
                    $oversize = true;

                    return 0;
                }

                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers, &$oversize) {
                $headers += strlen($line);
                if ($headers > 32768) {
                    $oversize = true;

                    return 0;
                }

                return strlen($line);
            },
        ]);
        try {
            $ok = curl_exec($curl) !== false;
            $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $certs = curl_getinfo($curl, CURLINFO_CERTINFO);
            $expiry = $certs[0]['Expire date'] ?? null;
            $expires = $expiry ? strtotime($expiry) : false;

            return [
                'available' => $ok && $status >= 200 && $status < 300,
                'http_status' => $status ?: null,
                'tls_expires_at' => $expires ? gmdate('Y-m-d H:i:s', $expires) : null,
                // Never persist cURL diagnostics: they can include request data.
                'error' => $oversize ? 'response_limit' : (! $ok ? 'transport_or_tls' : ($status >= 200 && $status < 300 ? null : 'http_status')),
            ];
        } finally {
            curl_close($curl);
        }
    }
}
