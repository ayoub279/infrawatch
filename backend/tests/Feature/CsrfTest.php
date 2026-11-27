<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CsrfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PreventRequestForgery::class, function ($app) {
            return new class($app, $app['encrypter']) extends PreventRequestForgery
            {
                protected function runningUnitTests()
                {
                    return false;
                }
            };
        });
    }

    public function test_login_rejects_missing_csrf_token(): void
    {
        $this->postJson('/api/login', ['email' => 'viewer@example.com', 'password' => 'test'])->assertStatus(419);
    }

    public function test_matching_csrf_token_reaches_login_validation(): void
    {
        $this->withSession(['_token' => 'test-csrf-token'])
            ->postJson('/api/login', ['_token' => 'test-csrf-token'])->assertStatus(422);
    }

    public function test_get_sets_xsrf_cookie_without_exposing_session(): void
    {
        $this->getJson('/api/csrf')->assertOk()->assertCookie('XSRF-TOKEN');
    }
}
