<?php

namespace Tests\Feature;

use App\Models\DriverRoute;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverDirectionControlTest extends TestCase
{
    use RefreshDatabase;

    protected User $driver;
    protected User $rider;
    protected DriverRoute $route;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = User::create([
            'name' => 'سائق التحكم بالاتجاه',
            'email' => 'driver-control@meshwar.local',
            'phone' => '0599555666',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);

        Vehicle::create([
            'driver_id' => $this->driver->id,
            'type' => 'bus',
            'seat_count' => 7,
            'license_plate' => '7-5555-99',
            'status' => 'active',
        ]);

        $this->route = DriverRoute::create([
            'driver_id' => $this->driver->id,
            'name' => 'رفح - غزة',
            'start_point' => 'دوار العودة',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'is_two_way' => true,
            'active_direction' => 'outbound',
            'stops' => [
                ['order' => 1, 'name' => 'دوار العودة', 'lat' => 31.2800, 'lng' => 34.2500, 'fare_ils' => 0.0],
                ['order' => 2, 'name' => 'مستشفى الشفاء', 'lat' => 31.5200, 'lng' => 34.4400, 'fare_ils' => 8.0],
            ],
        ]);

        $this->rider = User::create([
            'name' => 'راكب غير مخول',
            'email' => 'rider-unauth@meshwar.local',
            'phone' => '0599777888',
            'role' => 'rider',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_driver_can_update_active_direction(): void
    {
        // Switch to return leg
        $response = $this->actingAs($this->driver)->postJson('/api/driver/route/direction', [
            'direction' => 'return',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'active_direction' => 'return',
                'active_direction_arabic' => 'رحلة العودة',
            ]);

        $this->assertEquals('return', $this->route->fresh()->active_direction);

        // Switch back to outbound leg
        $response2 = $this->actingAs($this->driver)->postJson('/api/driver/route/direction', [
            'direction' => 'outbound',
        ]);

        $response2->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'active_direction' => 'outbound',
                'active_direction_arabic' => 'رحلة الذهاب',
            ]);

        $this->assertEquals('outbound', $this->route->fresh()->active_direction);
    }

    public function test_driver_cannot_set_invalid_direction(): void
    {
        $response = $this->actingAs($this->driver)->postJson('/api/driver/route/direction', [
            'direction' => 'diagonal',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['direction']);
    }

    public function test_rider_cannot_update_driver_direction(): void
    {
        $response = $this->actingAs($this->rider)->postJson('/api/driver/route/direction', [
            'direction' => 'return',
        ]);

        $response->assertStatus(403);
    }

    public function test_driver_can_save_route_with_is_two_way_setting(): void
    {
        $response = $this->actingAs($this->driver)->postJson('/api/driver/route', [
            'name' => 'خط اتجاه واحد تجريبي',
            'start_point' => 'دوار العودة',
            'first_trip_time' => '06:30',
            'is_two_way' => false,
            'stops' => [
                ['order' => 1, 'name' => 'دوار العودة', 'lat' => 31.2800, 'lng' => 34.2500, 'fare_ils' => 0.0],
                ['order' => 2, 'name' => 'مستشفى الشفاء', 'lat' => 31.5200, 'lng' => 34.4400, 'fare_ils' => 8.0],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertFalse($this->route->fresh()->is_two_way);
    }
}
