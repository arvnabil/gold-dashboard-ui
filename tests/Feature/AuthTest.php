<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_user_is_redirected_to_login_sec_01(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_registration_route_does_not_exist(): void
    {
        // Public registration must be permanently disabled
        $response = $this->get('/register');
        $response->assertStatus(404);

        $postResponse = $this->post('/register', [
            'name' => 'Attacker',
            'email' => 'attacker@evil.com',
            'password' => 'password123',
        ]);
        $postResponse->assertStatus(404);
    }

    public function test_owner_can_authenticate_and_access_dashboard(): void
    {
        $owner = User::factory()->create([
            'email' => 'nabil@analyst.local',
            'password' => bcrypt('password123'),
            'role' => 'owner',
        ]);

        $response = $this->post('/login', [
            'email' => 'nabil@analyst.local',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($owner);

        $dashboardResponse = $this->actingAs($owner)->get('/');
        $dashboardResponse->assertStatus(200);
    }
}

