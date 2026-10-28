<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\DriverRoute;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFareCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_automatically_computes_fare_from_route_stops_when_not_provided(): void
    {
        $driver = User::create([
            'name' => 'سائق الخط',
            'email' => 'line-driver@meshwar.local',
            'phone' => '0599111444',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);

        Vehicle::create([
            'driver_id' => $driver->id,
            'type' => 'bus',
            'seat_count' => 8,
            'license_plate' => '7-1234-99',
            'status' => 'active',
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'رفح - غزة',
            'start_point' => 'دوار العودة - رفح',
            'first_trip_time' => '07:30',
            'status' => 'on_route',
            'stops' => [
                ['order' => 1, 'name' => 'دوار العودة - رفح', 'fare_ils' => 0],
                ['order' => 2, 'name' => 'دوار بني سهيلا', 'fare_ils' => 5],
                ['order' => 3, 'name' => 'دير البلح', 'fare_ils' => 8],
                ['order' => 4, 'name' => 'مفترق السرايا - غزة', 'fare_ils' => 12],
            ],
        ]);

        $rider = User::create([
            'name' => 'راكب المشوار',
            'email' => 'fare-rider@meshwar.local',
            'phone' => '0599111555',
            'role' => 'rider',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($rider);

        // Booking 2 seats to دير البلح (stop fare: 8 ILS * 2 seats = 16 ILS) without explicit fare_ils
        $response = $this->postJson(route('api.bookings.store'), [
            'route_id' => $route->id,
            'seats' => 2,
            'pickup_stop' => 'دوار العودة - رفح',
            'dropoff_stop' => 'دير البلح',
        ]);

        $response->assertCreated();
        $booking = Booking::findOrFail($response->json('booking.id'));

        $this->assertEquals(16.0, (float) $booking->fare_ils, 'Fare must auto-calculate from stop price (8 * 2 = 16)');
    }

    public function test_driver_can_confirm_or_update_collected_fare_on_trip_completion(): void
    {
        $driver = User::create([
            'name' => 'سائق التوصيل',
            'email' => 'driver-complete@meshwar.local',
            'phone' => '0599111666',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'خط البلد',
            'start_point' => 'المحطة',
            'first_trip_time' => '08:00',
            'status' => 'on_route',
            'stops' => [
                ['order' => 1, 'name' => 'المحطة', 'fare_ils' => 0],
                ['order' => 2, 'name' => 'الجامعة', 'fare_ils' => 5],
            ],
        ]);

        $rider = User::create([
            'name' => 'طالب الجامعة',
            'email' => 'student@meshwar.local',
            'phone' => '0599111777',
            'role' => 'rider',
            'password' => bcrypt('password'),
        ]);

        $booking = Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 1,
            'pickup_stop' => 'المحطة',
            'dropoff_stop' => 'الجامعة',
            'fare_ils' => null, // Initially null
            'status' => 'boarded',
        ]);

        $this->actingAs($driver);

        // Driver completes booking with confirmed collected fare of 5 ILS
        $response = $this->postJson(route('api.bookings.complete', $booking->id), [
            'fare_ils' => 5.0,
        ]);

        $response->assertOk();
        $this->assertEquals(5.0, (float) $booking->fresh()->fare_ils);
        $this->assertEquals('completed', $booking->fresh()->status);
    }
}
