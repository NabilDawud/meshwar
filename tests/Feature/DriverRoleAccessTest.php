<?php

namespace Tests\Feature;

use App\Models\DriverRoute;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $driver;

    protected User $rider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = User::factory()->create([
            'role' => 'driver',
            'email' => 'driver-role@meshwar.local',
        ]);

        Vehicle::create([
            'driver_id' => $this->driver->id,
            'type' => 'car',
            'seat_count' => 4,
            'license_plate' => 'غزة - 8888',
            'status' => 'active',
        ]);

        DriverRoute::create([
            'driver_id' => $this->driver->id,
            'name' => 'مسار تجريبي',
            'start_point' => 'موقف الجنوب',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'is_active' => true,
            'stops' => [
                ['order' => 1, 'name' => 'موقف الجنوب', 'lat' => 31.3456, 'lng' => 34.3182, 'fare_ils' => 0],
                ['order' => 2, 'name' => 'البلد', 'lat' => 31.3500, 'lng' => 34.3200, 'fare_ils' => 5],
            ],
        ]);

        $this->rider = User::factory()->create([
            'role' => 'rider',
            'email' => 'rider-role@meshwar.local',
        ]);
    }

    public function test_rider_is_forbidden_from_all_driver_web_screens(): void
    {
        $driverPages = [
            '/driver',
            '/driver/vehicle',
            '/driver/route',
            '/driver/manifest',
            '/driver/offers',
            '/driver/history',
        ];

        foreach ($driverPages as $page) {
            $response = $this->actingAs($this->rider)->get($page);
            $response->assertStatus(403);
        }
    }

    public function test_driver_can_access_all_driver_web_screens(): void
    {
        $driverPages = [
            '/driver',
            '/driver/schedule',
            '/driver/vehicle',
            '/driver/route',
            '/driver/manifest',
            '/driver/offers',
            '/driver/history',
        ];

        foreach ($driverPages as $page) {
            $response = $this->actingAs($this->driver)->get($page);
            $response->assertStatus(200);
        }
    }
}
