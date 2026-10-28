<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\DriverRoute;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiderBookingsPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_bookings_page_preloads_initial_bookings_for_zero_latency(): void
    {
        $driver = User::create([
            'name' => 'كابتن محمد',
            'email' => 'driver-fast@meshwar.local',
            'phone' => '0599111888',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);

        Vehicle::create([
            'driver_id' => $driver->id,
            'type' => 'bus',
            'seat_count' => 8,
            'license_plate' => '7-1234-55',
            'status' => 'active',
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'خط صلاح الدين',
            'start_point' => 'دوار بني سهيلا',
            'first_trip_time' => '08:00',
            'status' => 'on_route',
            'stops' => [],
        ]);

        $rider = User::create([
            'name' => 'راكب سريع',
            'email' => 'rider-fast@meshwar.local',
            'phone' => '0599111999',
            'role' => 'rider',
            'password' => bcrypt('password'),
        ]);

        $booking = Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 2,
            'pickup_stop' => 'دوار بني سهيلا',
            'dropoff_stop' => 'غزة',
            'fare_ils' => 20.0,
            'status' => 'confirmed',
        ]);

        $this->actingAs($rider);

        $response = $this->get(route('rider.bookings'));

        $response->assertOk();
        $response->assertViewHas('initialBookings');

        $initialBookings = $response->viewData('initialBookings');
        $this->assertCount(1, $initialBookings);
        $this->assertEquals($booking->id, $initialBookings[0]['id']);
        $this->assertEquals(20.0, (float) $initialBookings[0]['fare_ils']);
        $this->assertEquals('confirmed', $initialBookings[0]['status']);
    }
}
