<?php

namespace Tests\Feature;

use App\Services\HttpProbe;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class HttpProbeTest extends TestCase
{
    private ?Process $server = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (! getenv('RUN_TRANSPORT_TESTS')) {
            $this->markTestSkipped('Socket tests run in Linux CI with RUN_TRANSPORT_TESTS=1.');
        }
        $this->server = new Process([PHP_BINARY, '-S', '127.0.0.1:18080', base_path('tests/fixtures/http.php')]);
        $this->server->start();
        $ready = false;
        for ($i = 0; $i < 30; $i++) {
            $socket = @fsockopen('127.0.0.1', 18080, $errno, $error, 0.1);
            if ($socket) {
                fclose($socket);
                $ready = true;
                break;
            }
            usleep(100000);
        }
        $this->assertTrue($ready, 'HTTP fixture did not start');
    }

    protected function tearDown(): void
    {
        $this->server?->stop(0);
        parent::tearDown();
    }

    private function probe(string $path): array
    {
        // Transport-only test; production calls are gated by DestinationPolicy in Monitor.
        return (new HttpProbe)->fetch('http://fixture.test:18080'.$path, ['host' => 'fixture.test', 'port' => 18080, 'scheme' => 'http'], '127.0.0.1');
    }

    public function test_http_2xx_is_available(): void
    {
        $r = $this->probe('/ok');
        $this->assertTrue($r['available']);
        $this->assertSame(200, $r['http_status']);
    }

    public function test_redirects_are_not_followed(): void
    {
        $r = $this->probe('/redirect');
        $this->assertFalse($r['available']);
        $this->assertSame(302, $r['http_status']);
    }

    public function test_body_limit_aborts_transfer(): void
    {
        $r = $this->probe('/large');
        $this->assertFalse($r['available']);
        $this->assertSame('response_limit', $r['error']);
    }

    public function test_slow_response_times_out(): void
    {
        $start = microtime(true);
        $r = $this->probe('/slow');
        $this->assertFalse($r['available']);
        $this->assertLessThan(6.5, microtime(true) - $start);
    }
}
