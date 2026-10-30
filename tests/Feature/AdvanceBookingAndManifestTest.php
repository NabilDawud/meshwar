<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\DriverRoute;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvanceBookingAndManifestTest extends TestCase
{
    use RefreshDatabase;

    protected User $driver;

    protected User $rider;

    protected Vehicle $vehicle;

    protected DriverRoute $route;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = User::create([
            'name' => 'سائق الحجز المسبق',
            'email' => 'driver-manifest-test@meshwar.local',
            'phone' => '0599000111',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);

        $this->vehicle = Vehicle::create([
            'driver_id' => $this->driver->id,
            'type' => 'bus',
            'seat_count' => 7,
            'license_plate' => '7-0011-22',
            'status' => 'active',
        ]);

        $this->route = DriverRoute::create([
            'driver_id' => $this->driver->id,
            'name' => 'خط غزة - خانيونس',
            'start_point' => 'دوار العودة',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'is_active' => true,
            'stops' => [
                ['order' => 1, 'name' => 'دوار العودة', 'lat' => 31.2800, 'lng' => 34.2500, 'fare_ils' => 0.0],
                ['order' => 2, 'name' => 'دوار بني سهيلا', 'lat' => 31.3456, 'lng' => 34.3182, 'fare_ils' => 5.0],
                ['order' => 3, 'name' => 'مستشفى الشفاء', 'lat' => 31.5200, 'lng' => 34.4400, 'fare_ils' => 10.0],
            ],
        ]);

        $this->rider = User::create([
            'name' => 'راكب الحجز المسبق',
            'email' => 'rider-manifest-test@meshwar.local',
            'phone' => '0599000222',
            'role' => 'rider',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_rider_can_book_advance_seat_for_tomorrow(): void
    {
        $tomorrowDate = Carbon::tomorrow()->toDateString();

        $payload = [
            'route_id' => $this->route->id,
            'seats' => 2,
            'travel_date' => $tomorrowDate,
            'pickup_stop' => 'دوار العودة',
            'dropoff_stop' => 'مستشفى الشفاء',
        ];

        $response = $this->actingAs($this->rider)->postJson('/api/bookings', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'ok' => true,
                'booking' => [
                    'seats' => 2,
                    'status' => 'confirmed',
                    'travel_date' => $tomorrowDate,
                ],
            ]);

        $this->assertDatabaseHas('bookings', [
            'rider_id' => $this->rider->id,
            'route_id' => $this->route->id,
            'seats' => 2,
            'status' => 'confirmed',
        ]);

        $savedBooking = Booking::where('rider_id', $this->rider->id)->first();
        $this->assertNotNull($savedBooking);
        $this->assertEquals($tomorrowDate, Carbon::parse($savedBooking->travel_date)->toDateString());
    }

    public function test_booking_tomorrow_does_not_affect_today_free_seats(): void
    {
        $todayDate = Carbon::today()->toDateString();
        $tomorrowDate = Carbon::tomorrow()->toDateString();

        // 1. Reserve 4 seats for tomorrow
        $resTomorrow = $this->actingAs($this->rider)->postJson('/api/bookings', [
            'route_id' => $this->route->id,
            'seats' => 4,
            'travel_date' => $tomorrowDate,
            'pickup_stop' => 'دوار العودة',
            'dropoff_stop' => 'مستشفى الشفاء',
        ]);
        $resTomorrow->assertStatus(201);

        // 2. Today should still have all 7 seats free: reserving 5 seats for today MUST succeed
        $resToday = $this->actingAs($this->rider)->postJson('/api/bookings', [
            'route_id' => $this->route->id,
            'seats' => 5,
            'travel_date' => $todayDate,
            'pickup_stop' => 'دوار العودة',
            'dropoff_stop' => 'مستشفى الشفاء',
        ]);
        $resToday->assertStatus(201);

        // 3. Reserving 4 MORE seats for tomorrow must fail because 4 + 4 > 7 capacity
        $resOverflow = $this->actingAs($this->rider)->postJson('/api/bookings', [
            'route_id' => $this->route->id,
            'seats' => 4,
            'travel_date' => $tomorrowDate,
            'pickup_stop' => 'دوار العودة',
            'dropoff_stop' => 'مستشفى الشفاء',
        ]);
        $resOverflow->assertStatus(422)
            ->assertJson([
                'ok' => false,
                'free_seats' => 3,
            ]);
    }

    public function test_booking_past_date_is_rejected(): void
    {
        $yesterday = Carbon::yesterday()->toDateString();

        $response = $this->actingAs($this->rider)->postJson('/api/bookings', [
            'route_id' => $this->route->id,
            'seats' => 1,
            'travel_date' => $yesterday,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['travel_date']);
    }

    public function test_driver_manifest_filters_by_date_and_returns_counters(): void
    {
        $todayDate = Carbon::today()->toDateString();
        $tomorrowDate = Carbon::tomorrow()->toDateString();

        // Booking 1: Today
        Booking::create([
            'rider_id' => $this->rider->id,
            'route_id' => $this->route->id,
            'seats' => 1,
            'pickup_stop' => 'دوار العودة',
            'dropoff_stop' => 'مستشفى الشفاء',
            'total_fare_ils' => 10.0,
            'status' => 'confirmed',
            'travel_date' => $todayDate,
        ]);

        // Booking 2: Tomorrow
        Booking::create([
            'rider_id' => $this->rider->id,
            'route_id' => $this->route->id,
            'seats' => 2,
            'pickup_stop' => 'دوار العودة',
            'dropoff_stop' => 'دوار بني سهيلا',
            'total_fare_ils' => 10.0,
            'status' => 'confirmed',
            'travel_date' => $tomorrowDate,
        ]);

        // Query manifest for TODAY
        $resToday = $this->actingAs($this->driver)->getJson("/api/driver/manifest?date={$todayDate}");
        $resToday->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'is_today' => true,
                'booked_seats' => 1,
                'free_seats' => 6,
                'today_count' => 1,
                'tomorrow_count' => 1,
            ]);
        $this->assertCount(1, $resToday->json('manifest'));

        // Query manifest for TOMORROW
        $resTomorrow = $this->actingAs($this->driver)->getJson("/api/driver/manifest?date={$tomorrowDate}");
        $resTomorrow->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'is_today' => false,
                'booked_seats' => 2,
                'free_seats' => 5,
                'today_count' => 1,
                'tomorrow_count' => 1,
            ]);
        $this->assertCount(1, $resTomorrow->json('manifest'));
    }
}
