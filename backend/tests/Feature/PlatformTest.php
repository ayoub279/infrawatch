<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class PlatformTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $u = User::factory()->create();
        $u->role = $role;
        $u->save();

        return $u;
    }

    public function test_guests_cannot_read_or_mutate(): void
    {
        $this->getJson('/api/services')->assertUnauthorized();
        $this->postJson('/api/services', [])->assertUnauthorized();
        $this->getJson('/api/audit')->assertUnauthorized();
    }

    public function test_login_logout_and_no_registration(): void
    {
        $u = $this->user('viewer');
        $u->password = Hash::make('a-long-test-password');
        $u->save();
        $this->postJson('/api/login', ['email' => $u->email, 'password' => 'wrong'])->assertStatus(422);
        $this->postJson('/api/login', ['email' => $u->email, 'password' => 'a-long-test-password'])->assertOk()->assertJsonPath('role', 'viewer')->assertJsonMissingPath('password');
        $this->getJson('/api/me')->assertOk();
        $this->postJson('/api/logout')->assertNoContent();
        $this->assertGuest();
        $this->postJson('/api/register', [])->assertNotFound();
    }

    public function test_viewer_can_read_but_cannot_manage_services_or_read_audit(): void
    {
        $this->actingAs($this->user('viewer'));
        $this->getJson('/api/services')->assertOk();
        $this->postJson('/api/services', ['name' => 'X', 'url' => 'https://example.com', 'enabled' => true])->assertForbidden();
        $this->putJson('/api/services/1', [])->assertForbidden();
        $this->deleteJson('/api/services/1')->assertForbidden();
        $this->getJson('/api/audit')->assertForbidden();
        $this->getJson('/api/allowed-hosts')->assertForbidden();
    }

    public function test_admin_crud_and_audit_without_secrets(): void
    {
        config(['infrawatch.hosts' => ['example.com']]);
        $u = $this->user('admin');
        $this->actingAs($u);
        $id = $this->postJson('/api/services', ['name' => 'Example', 'url' => 'https://example.com', 'enabled' => true, 'password' => 'DO-NOT-LOG'])
            ->assertCreated()->json('id');
        $this->putJson("/api/services/$id", ['name' => 'Changed', 'url' => 'https://example.com/status', 'enabled' => false])->assertNoContent();
        $this->getJson('/api/services')->assertJsonPath('0.name', 'Changed');
        $this->deleteJson("/api/services/$id")->assertNoContent();
        $this->assertDatabaseCount('audit_logs', 3);
        $this->assertStringNotContainsString('DO-NOT-LOG', DB::table('audit_logs')->get()->toJson());
        $this->postJson('/api/services', ['name' => 'Bad', 'url' => 'https://evil.test', 'enabled' => true])->assertStatus(422);
        $this->postJson('/api/services', ['name' => 'Bad', 'url' => 'https://user:secret@example.com', 'enabled' => true])->assertStatus(422);
    }

    public function test_authenticated_viewer_can_acknowledge_and_resolve(): void
    {
        $this->actingAs($this->user('viewer'));
        $s = DB::table('services')->insertGetId(['name' => 'X', 'url' => 'https://example.com']);
        $id = DB::table('incidents')->insertGetId(['service_id' => $s, 'opened_at' => now(), 'status' => 'open']);
        $this->patchJson("/api/incidents/$id", ['action' => 'acknowledge'])->assertNoContent();
        $this->patchJson("/api/incidents/$id", ['action' => 'acknowledge'])->assertConflict();
        $this->patchJson("/api/incidents/$id", ['action' => 'resolve'])->assertNoContent();
        $this->patchJson("/api/incidents/$id", ['action' => 'resolve'])->assertConflict();
        $this->assertDatabaseHas('incidents', ['id' => $id, 'status' => 'resolved']);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'unknown@example.com', 'password' => 'wrong'])->assertStatus(422);
        }
        $this->postJson('/api/login', ['email' => 'unknown@example.com', 'password' => 'wrong'])->assertStatus(429);
    }
}
