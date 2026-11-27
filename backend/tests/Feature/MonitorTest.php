<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DestinationPolicy;
use App\Services\DnsResolver;
use App\Services\HttpProbe;
use App\Services\Monitor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

final class MonitorTest extends TestCase
{
    use RefreshDatabase;

    private function service(): object
    {
        $id = DB::table('services')->insertGetId(['name' => 'Example', 'url' => 'https://example.com', 'enabled' => true]);

        return DB::table('services')->find($id);
    }

    private function checkResult(bool $up): array
    {
        return ['target_url' => 'https://example.com', 'available' => $up, 'http_status' => $up ? 200 : 503, 'response_ms' => 20, 'dns_addresses' => '["93.184.216.34"]', 'tls_expires_at' => null, 'error' => $up ? null : 'http_status', 'checked_at' => now()];
    }

    public function test_failure_deduplicates_acknowledged_incident_and_reopens_after_resolution(): void
    {
        $s = $this->service();
        $m = app(Monitor::class);
        $m->persist($s->id, $this->checkResult(false));
        $m->persist($s->id, $this->checkResult(false));
        $this->assertDatabaseCount('incidents', 1);
        DB::table('incidents')->update(['status' => 'acknowledged', 'acknowledged_at' => now()]);
        $m->persist($s->id, $this->checkResult(false));
        $m->persist($s->id, $this->checkResult(true));
        $this->assertDatabaseCount('incidents', 1);
        DB::table('incidents')->update(['status' => 'resolved', 'resolved_at' => now()]);
        $m->persist($s->id, $this->checkResult(false));
        $this->assertDatabaseCount('incidents', 2);
        $this->assertDatabaseCount('checks', 5);
    }

    public function test_database_unique_index_blocks_second_active_incident(): void
    {
        $s = $this->service();
        $data = ['service_id' => $s->id, 'status' => 'open', 'opened_at' => now()];
        DB::table('incidents')->insert($data);
        $this->expectException(QueryException::class);
        DB::table('incidents')->insert($data);
    }

    public function test_mixed_dns_blocks_transport_and_records_failure(): void
    {
        config(['infrawatch.hosts' => ['example.com']]);
        $dns = Mockery::mock(DnsResolver::class);
        $dns->shouldReceive('resolve')->once()->andReturn(['93.184.216.34', '127.0.0.1']);
        $http = Mockery::mock(HttpProbe::class);
        $http->shouldNotReceive('fetch');
        $r = (new Monitor(new DestinationPolicy, $dns, $http))->check($this->service());
        $this->assertFalse($r['available']);
        $this->assertSame('destination_rejected', $r['error']);
    }

    public function test_policy_is_rechecked_at_execution_and_ip_is_pinned(): void
    {
        config(['infrawatch.hosts' => ['example.com']]);
        $s = $this->service();
        $dns = Mockery::mock(DnsResolver::class);
        $dns->shouldReceive('resolve')->once()->andReturn(['93.184.216.34']);
        $http = Mockery::mock(HttpProbe::class);
        $http->shouldReceive('fetch')->once()->with($s->url, ['host' => 'example.com', 'port' => 443, 'scheme' => 'https'], '93.184.216.34')
            ->andReturn(['available' => true, 'http_status' => 200, 'tls_expires_at' => null, 'error' => null]);
        $m = new Monitor(new DestinationPolicy, $dns, $http);
        $this->assertTrue($m->check($s)['available']);
        config(['infrawatch.hosts' => []]);
        $this->assertSame('destination_rejected', $m->check($s)['error']);
    }

    public function test_destination_change_discards_in_flight_result(): void
    {
        $s = $this->service();
        DB::table('services')->where('id', $s->id)->update(['url' => 'https://example.com/new']);
        app(Monitor::class)->persist($s->id, $this->checkResult(false));
        $this->assertDatabaseCount('checks', 0);
        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_history_returns_public_data_with_iso_timestamps(): void
    {
        $s = $this->service();
        app(Monitor::class)->persist($s->id, $this->checkResult(true));
        $this->actingAs(User::factory()->create())
            ->getJson("/api/services/{$s->id}/checks")->assertOk()
            ->assertJsonPath('0.available', true)
            ->assertJsonPath('0.dns_addresses.0', '93.184.216.34');
    }

    public function test_disabled_service_is_not_persisted(): void
    {
        $s = $this->service();
        DB::table('services')->where('id', $s->id)->update(['enabled' => false]);
        app(Monitor::class)->persist($s->id, $this->checkResult(false));
        $this->assertDatabaseCount('checks', 0);
    }
}
