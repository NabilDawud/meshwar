<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Milestone1Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Test /api/health endpoint returns 200 with JSON {ok: true, commit: ...}.
     */
    public function test_health_endpoint_returns_ok_and_commit(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure(['ok', 'commit'])
            ->assertJson(['ok' => true]);
    }

    /**
     * Test /api/qa-login endpoint in QA_MODE logs in driver and rider without credentials.
     */
    public function test_qa_login_endpoint_authenticates_driver_and_rider(): void
    {
        config(['app.qa_mode' => '1']);
        putenv('QA_MODE=1');
        $_ENV['QA_MODE'] = '1';

        // Test QA login as driver
        $response = $this->getJson('/api/qa-login?as=driver');
        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'role' => 'driver',
            ]);
        $this->assertAuthenticated();
        $this->assertEquals('driver', auth()->user()->role);

        // Test QA login as rider
        $response = $this->getJson('/api/qa-login?as=rider');
        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'role' => 'rider',
            ]);
        $this->assertEquals('rider', auth()->user()->role);
    }

    /**
     * Test guest can access welcome home screen.
     */
    public function test_guest_can_access_welcome_home_screen(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('مشوار');
        $response->assertSee('غزة');
    }

    /**
     * Test login view renders Google Sign In and dev options.
     */
    public function test_login_view_renders_correctly(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('المتابعة باستخدام Google');
        $response->assertSee('auth/google');
    }

    /**
     * Test authenticated user without a role is redirected to /auth/role-choice.
     */
    public function test_authenticated_user_without_role_is_redirected_to_role_choice(): void
    {
        $user = User::factory()->create([
            'role' => null,
        ]);

        $response = $this->actingAs($user)->get('/rides');

        $response->assertRedirect('/auth/role-choice');
    }

    /**
     * Test user can select a role and enter phone number which persists to database.
     */
    public function test_user_can_select_role_and_persists_to_database(): void
    {
        $user = User::factory()->create([
            'role' => null,
            'phone' => null,
        ]);

        // Select driver role with valid Palestinian phone
        $response = $this->actingAs($user)->post('/auth/role-choice', [
            'role' => 'driver',
            'phone' => '0599123456',
        ]);

        $response->assertRedirect(route('driver.dashboard'));
        $this->assertEquals('driver', $user->fresh()->role);
        $this->assertEquals('0599123456', $user->fresh()->phone);

        // Switch to rider role
        $response = $this->actingAs($user)->post('/auth/role-choice', [
            'role' => 'rider',
            'phone' => '0569876543',
        ]);

        $response->assertRedirect(route('rider.home'));
        $this->assertEquals('rider', $user->fresh()->role);
        $this->assertEquals('0569876543', $user->fresh()->phone);
    }

    /**
     * Test role choice validates Palestinian mobile numbers.
     */
    public function test_role_choice_requires_valid_palestinian_phone(): void
    {
        $user = User::factory()->create([
            'role' => null,
            'phone' => null,
        ]);

        // Invalid phone number
        $response = $this->actingAs($user)->post('/auth/role-choice', [
            'role' => 'rider',
            'phone' => '12345',
        ]);

        $response->assertSessionHasErrors(['phone']);
        $this->assertNull($user->fresh()->role);
    }

    /**
     * Test mock OAuth flow creates user and redirects appropriately.
     */
    public function test_mock_oauth_flow_authenticates_user(): void
    {
        $response = $this->get('/auth/google/mock?role=rider');

        $response->assertRedirect(route('rider.home'));
        $this->assertAuthenticated();
        $this->assertEquals('rider', auth()->user()->role);
    }
}
