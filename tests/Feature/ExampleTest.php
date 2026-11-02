<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the guest is redirected to login, and authenticated owner gets 200.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $guestResponse = $this->get('/');
        $guestResponse->assertRedirect('/login');

        $owner = User::factory()->create([
            'role' => 'owner',
        ]);

        $authResponse = $this->actingAs($owner)->get('/');
        $authResponse->assertStatus(200);
    }
}
