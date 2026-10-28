<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\DriverRoute;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SegmentFareCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected DriverRoute $route;

    protected User $driver;

    protected User $rider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = User::create([
            'name' => 'سائق الخط',
            'email' => 'driver-fares@meshwar.local',
            'phone' => '0599111222',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);

        Vehicle::create([
            'driver_id' => $this->driver->id,
            'type' => 'bus',
            'seat_count' => 7,
            'license_plate' => '7-1234-99',
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
                ['order' => 2, 'name' => 'مفترق خربة العدس', 'lat' => 31.3000, 'lng' => 34.2700, 'fare_ils' => 3.0],
                ['order' => 3, 'name' => 'دوار بني سهيلا', 'lat' => 31.3456, 'lng' => 34.3182, 'fare_ils' => 5.0],
                ['order' => 4, 'name' => 'مستشفى الشفاء', 'lat' => 31.5200, 'lng' => 34.4400, 'fare_ils' => 8.0],
            ],
        ]);

        $this->rider = User::create([
            'name' => 'راكب التجربة',
            'email' => 'rider-fares@meshwar.local',
            'phone' => '0599333444',
            'role' => 'rider',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_sub_route_fare_calculation_outbound_intermediate(): void
    {
        // Segment B -> C (خربة العدس 3.0 to بني سهيلا 5.0) => 2.0 ILS
        $fare = $this->route->calculateSegmentFare('مفترق خربة العدس', 'دوار بني سهيلا', 1);
        $this->assertEquals(2.0, $fare);
    }

    public function test_sub_route_fare_calculation_return_intermediate_is_symmetrical(): void
    {
        // Return Segment C -> B (بني سهيلا 5.0 to خربة العدس 3.0) => 2.0 ILS
        $fare = $this->route->calculateSegmentFare('دوار بني سهيلا', 'مفترق خربة العدس', 1);
        $this->assertEquals(2.0, $fare, 'Return direction must have identical symmetrical fare to outbound');
    }

    public function test_sub_route_fare_calculation_with_multiple_seats(): void
    {
        // Segment B -> C for 3 seats: 2.0 * 3 = 6.0 ILS
        $fare = $this->route->calculateSegmentFare('مفترق خربة العدس', 'دوار بني سهيلا', 3);
        $this->assertEquals(6.0, $fare);
    }

    public function test_sub_route_fare_calculation_from_terminal_origin(): void
    {
        // Segment A -> B (دوار العودة 0.0 to خربة العدس 3.0) => 3.0 ILS
        $fare = $this->route->calculateSegmentFare('دوار العودة', 'مفترق خربة العدس', 1);
        $this->assertEquals(3.0, $fare);
    }

    public function test_full_route_fare_calculation_end_to_end(): void
    {
        // Full Corridor A -> D (دوار العودة 0.0 to الشفاء 8.0) => 8.0 ILS
        $fare = $this->route->calculateSegmentFare('دوار العودة', 'مستشفى الشفاء', 1);
        $this->assertEquals(8.0, $fare);
    }

    public function test_booking_controller_persists_calculated_segment_fare(): void
    {
        $response = $this->actingAs($this->rider)->postJson('/api/bookings', [
            'route_id' => $this->route->id,
            'seats' => 2,
            'pickup_stop' => 'مفترق خربة العدس',
            'dropoff_stop' => 'دوار بني سهيلا',
        ]);

        $response->assertStatus(201);
        $booking = Booking::where('rider_id', $this->rider->id)->latest()->first();
        $this->assertNotNull($booking);
        // Unit fare 2.0 * 2 seats = 4.0 ILS
        $this->assertEquals(4.0, (float) $booking->fare_ils);
    }
}
