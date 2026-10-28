<?php

namespace Tests\Feature;

use App\Models\DriverRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationBroadcastSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_position_requires_valid_gaza_coordinates_and_does_not_accept_empty(): void
    {
        $driver = User::create([
            'name' => 'سائق غزة',
            'email' => 'driver-loc@meshwar.local',
            'phone' => '0599111222',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'خط الساحل - الرشيد',
            'start_point' => 'دوار الميناء - غزة',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'last_landmark' => 'دوار الميناء - غزة',
            'last_latitude' => 31.5200,
            'last_longitude' => 34.4400,
            'stops' => [
                ['order' => 1, 'name' => 'دوار الميناء - غزة', 'lat' => 31.5200, 'lng' => 34.4400, 'fare_ils' => 0],
                ['order' => 2, 'name' => 'دير البلح', 'lat' => 31.4170, 'lng' => 34.3500, 'fare_ils' => 6],
            ],
        ]);

        $this->actingAs($driver);

        // Submitting without coords must fail with 422 and NOT overwrite the landmark
        $response = $this->postJson(route('api.driver.position'), []);
        $response->assertStatus(422);

        $route->refresh();
        $this->assertEquals('دوار الميناء - غزة', $route->last_landmark, 'Landmark must remain intact and not corrupted');
        $this->assertEquals(31.5200, (float) $route->last_latitude);
    }

    public function test_valid_gps_updates_landmark_and_coordinates_accurately(): void
    {
        $driver = User::create([
            'name' => 'سائق الرشيد',
            'email' => 'driver-rashid@meshwar.local',
            'phone' => '0599111333',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'خط الساحل',
            'start_point' => 'دوار الميناء',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'stops' => [],
        ]);

        $this->actingAs($driver);

        $response = $this->postJson(route('api.driver.position'), [
            'lat' => 31.4172,
            'lng' => 34.3512,
            'landmark' => 'مفترق دير البلح - شارع الرشيد',
            'is_trip_active' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('position.landmark', 'مفترق دير البلح - شارع الرشيد');

        $route->refresh();
        $this->assertEquals(31.4172, (float) $route->last_latitude);
        $this->assertEquals(34.3512, (float) $route->last_longitude);
        $this->assertEquals('مفترق دير البلح - شارع الرشيد', $route->last_landmark);
    }
}
