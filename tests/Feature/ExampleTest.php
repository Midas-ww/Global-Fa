<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/login?local=1');

        $response->assertStatus(200);
    }

    public function test_login_redirects_to_portal(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(302);
    }

    public function test_logout_redirects_to_portal_logout(): void
    {
        $response = $this->post('/logout');

        $response->assertStatus(302);
        $this->assertStringContainsString('localhost:8000/logout', $response->headers->get('Location'));
    }

    public function test_portal_redirects_to_superapp_portal(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/sso/portal');

        $response->assertStatus(302);
        $this->assertStringContainsString('localhost:8000/sso/callback', $response->headers->get('Location'));
        $this->assertStringContainsString('target=portal', $response->headers->get('Location'));
    }
}
