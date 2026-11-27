<?php

namespace Tests\Unit;

use App\Services\DestinationPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DestinationPolicyTest extends TestCase
{
    #[DataProvider('urls')]
    public function test_rejects_unsafe_urls(string $url): void
    {
        config(['infrawatch.hosts' => ['example.com']]);
        $this->expectException(\InvalidArgumentException::class);
        (new DestinationPolicy)->parse($url);
    }

    public static function urls(): array
    {
        return array_map(fn ($u) => [$u], [
            'file:///etc/passwd', 'ftp://example.com', 'http://127.0.0.1', 'http://2130706433',
            'https://example.com.evil.test', 'https://evil.example.com', 'https://example.com:8443',
            'http://example.com:443', 'https://example.com:80', 'https://u:p@example.com',
            'https://example.com?token=secret', 'https://example.com#x', 'https://example.com.',
            "https://example.com\n", 'https://example.com\\@evil.test', 'http://[::1]', 'http://169.254.169.254',
        ]);
    }

    #[DataProvider('addresses')]
    public function test_rejects_non_public_addresses(string $ip): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new DestinationPolicy)->validateAddresses([$ip]);
    }

    public static function addresses(): array
    {
        return array_map(fn ($i) => [$i], ['127.0.0.1', '10.1.2.3', '172.16.1.1', '192.168.1.1', '169.254.169.254', '100.100.100.200',
            '0.0.0.0', '224.0.0.1', '198.18.0.1', '192.0.2.2', '::1', 'fe80::1', 'fc00::1', '::ffff:127.0.0.1', '2001:db8::1', '2002:7f00:1::']);
    }

    public function test_exact_hostname_and_public_addresses_accepted(): void
    {
        config(['infrawatch.hosts' => ['example.com']]);
        $p = new DestinationPolicy;
        $this->assertSame('example.com', $p->parse('https://example.com/health')['host']);
        $p->validateAddresses(['93.184.216.34', '2606:4700:4700::1111']);
    }

    public function test_mixed_dns_answer_and_empty_answer_rejected(): void
    {
        $p = new DestinationPolicy;
        try {
            $p->validateAddresses(['93.184.216.34', '127.0.0.1']);
            $this->fail();
        } catch (\InvalidArgumentException) {
        }
        $this->expectException(\InvalidArgumentException::class);
        $p->validateAddresses([]);
    }
}
