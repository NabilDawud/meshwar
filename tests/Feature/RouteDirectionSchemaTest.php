<?php

namespace Tests\Feature;

use App\Models\DriverRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteDirectionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_route_has_is_two_way_and_active_direction_with_defaults(): void
    {
        $driver = User::create([
            'name' => 'سائق المسار',
            'email' => 'driver-dir@meshwar.local',
            'phone' => '0599222111',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'رفح - غزة',
            'start_point' => 'دوار العودة',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'stops' => [],
        ]);

        $this->assertTrue($route->is_two_way, 'is_two_way must default to true for round-trip operation');
        $this->assertEquals('outbound', $route->active_direction, 'active_direction must default to outbound');
        $this->assertEquals('رحلة الذهاب', $route->active_direction_arabic);

        // Can update to return leg
        $route->update(['active_direction' => 'return']);
        $this->assertEquals('return', $route->fresh()->active_direction);
        $this->assertEquals('رحلة العودة', $route->fresh()->active_direction_arabic);
    }
}
