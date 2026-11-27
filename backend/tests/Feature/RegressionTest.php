<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_does_not_grant_cross_origin_access(): void
    {
        $this->getJson('/api/health', ['Origin' => 'https://other.test'])
            ->assertOk()->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_active_incidents_are_not_hidden_by_recent_closed_history(): void
    {
        $this->actingAs(User::factory()->create());
        $s = DB::table('services')->insertGetId(['name' => 'X', 'url' => 'https://example.com']);
        $id = DB::table('incidents')->insertGetId(['service_id' => $s, 'opened_at' => now(), 'status' => 'open']);
        for ($i = 0; $i < 101; $i++) {
            DB::table('incidents')->insert(['service_id' => $s, 'opened_at' => now(), 'status' => 'resolved', 'resolved_at' => now()]);
        }
        $this->getJson('/api/incidents')->assertOk()->assertJsonCount(100)->assertJsonPath('0.id', $id)->assertJsonPath('0.status', 'open');
    }

    public function test_demo_seed_is_idempotent_and_does_not_fabricate_checks(): void
    {
        config(['infrawatch.hosts' => ['example.com'], 'infrawatch.demo_password' => 'generated-test-only-password']);
        $this->seed();
        $this->seed();
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('services', 1);
        $this->assertDatabaseCount('checks', 0);
        $this->assertDatabaseCount('audit_logs', 3);
        $this->assertStringNotContainsString('generated-test-only-password', DB::table('audit_logs')->get()->toJson());
    }

    public function test_demo_seed_refuses_production(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(\RuntimeException::class);
        app(DatabaseSeeder::class)->run();
    }
}
