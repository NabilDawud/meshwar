<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\DriverRoute;
use App\Models\DriverSchedule;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvanceDateVehicleSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $driver1;

    protected User $driver2;

    protected Vehicle $vehicle1;

    protected Vehicle $vehicle2;

    protected DriverRoute $routeSalahAlDin;

    protected DriverRoute $routeAlRasheed;

    protected function setUp(): void
    {
        parent::setUp();

        // Driver 1: Has 2 routes
        $this->driver1 = User::create([
            'name' => 'سائق الخطوط المتعددة',
            'email' => 'driver1-advance@meshwar.local',
            'phone' => '0599111222',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);

        $this->vehicle1 = Vehicle::create([
            'driver_id' => $this->driver1->id,
            'type' => 'bus',
            'seat_count' => 7,
            'license_plate' => '7-1111-22',
            'status' => 'active',
        ]);

        $this->routeSalahAlDin = DriverRoute::create([
            'driver_id' => $this->driver1->id,
            'name' => 'خط صلاح الدين',
            'start_point' => 'رفح - دوار العودة',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'is_active' => true,
            'stops' => [
                ['order' => 1, 'name' => 'دوار العودة', 'lat' => 31.2800, 'lng' => 34.2500, 'fare_ils' => 0.0],
                ['order' => 2, 'name' => 'دوار بني سهيلا', 'lat' => 31.3456, 'lng' => 34.3182, 'fare_ils' => 5.0],
                ['order' => 3, 'name' => 'مستشفى الشفاء', 'lat' => 31.5200, 'lng' => 34.4400, 'fare_ils' => 10.0],
            ],
            'last_latitude' => 31.3456,
            'last_longitude' => 34.3182,
            'last_landmark' => 'دوار بني سهيلا',
            'last_position_at' => now(),
        ]);

        $this->routeAlRasheed = DriverRoute::create([
            'driver_id' => $this->driver1->id,
            'name' => 'خط الرشيد الساحلي',
            'start_point' => 'ميناء غزة',
            'first_trip_time' => '08:00',
            'status' => 'on_route',
            'is_active' => false,
            'stops' => [
                ['order' => 1, 'name' => 'ميناء غزة', 'lat' => 31.5270, 'lng' => 34.4330, 'fare_ils' => 0.0],
                ['order' => 2, 'name' => 'دير البلح', 'lat' => 31.4170, 'lng' => 34.3500, 'fare_ils' => 6.0],
            ],
        ]);

        // Driver 2: Single route, will be off-duty tomorrow
        $this->driver2 = User::create([
            'name' => 'سائق الإجازة',
            'email' => 'driver2-advance@meshwar.local',
            'phone' => '0599333444',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);

        $this->vehicle2 = Vehicle::create([
            'driver_id' => $this->driver2->id,
            'type' => 'car',
            'seat_count' => 4,
            'license_plate' => '4-3333-44',
            'status' => 'active',
        ]);

        DriverRoute::create([
            'driver_id' => $this->driver2->id,
            'name' => 'خط خانيونس السريع',
            'start_point' => 'خانيونس البلد',
            'first_trip_time' => '06:30',
            'status' => 'on_route',
            'is_active' => true,
            'stops' => [
                ['order' => 1, 'name' => 'خانيونس البلد', 'lat' => 31.3460, 'lng' => 34.3000, 'fare_ils' => 0.0],
                ['order' => 2, 'name' => 'دوار النصيرات', 'lat' => 31.4500, 'lng' => 34.3900, 'fare_ils' => 7.0],
            ],
        ]);
    }

    public function test_can_search_vehicles_for_tomorrow_resolving_driver_scheduled_route(): void
    {
        $tomorrow = Carbon::tomorrow();
        $tomorrowDate = $tomorrow->toDateString();
        $tomorrowDayOfWeek = $tomorrow->dayOfWeek;

        // Driver 1 is scheduled on Route Al-Rasheed tomorrow from 08:00 to 15:00
        DriverSchedule::create([
            'driver_id' => $this->driver1->id,
            'day_of_week' => $tomorrowDayOfWeek,
            'route_id' => $this->routeAlRasheed->id,
            'start_time' => '08:00',
            'end_time' => '15:00',
            'is_day_off' => false,
        ]);

        // Driver 2 is on day off tomorrow
        DriverSchedule::create([
            'driver_id' => $this->driver2->id,
            'day_of_week' => $tomorrowDayOfWeek,
            'route_id' => null,
            'start_time' => '00:00',
            'end_time' => '00:00',
            'is_day_off' => true,
        ]);

        $response = $this->getJson("/api/rides/active-vehicles?date={$tomorrowDate}");

        $response->assertStatus(200)
            ->assertJson(['ok' => true]);

        $vehicles = $response->json('vehicles');

        // Driver 2 must be excluded because is_day_off = true
        $driverIds = array_column($vehicles, 'driver_id');
        $this->assertContains($this->driver1->id, $driverIds);
        $this->assertNotContains($this->driver2->id, $driverIds);

        // Driver 1 must resolve to routeAlRasheed
        $d1Data = collect($vehicles)->firstWhere('driver_id', $this->driver1->id);
        $this->assertEquals($this->routeAlRasheed->id, $d1Data['route_id']);
        $this->assertEquals('خط الرشيد الساحلي', $d1Data['route_name']);
        $this->assertTrue($d1Data['is_future_date']);
        $this->assertNull($d1Data['last_latitude']);
        $this->assertEquals('رحلة مجدولة', $d1Data['position_age_arabic']);
        $this->assertEquals('08:00', $d1Data['operating_window']['start_time']);
        $this->assertEquals('15:00', $d1Data['operating_window']['end_time']);
    }

    public function test_time_window_filtering_matches_driver_operating_hours(): void
    {
        $tomorrow = Carbon::tomorrow();
        $tomorrowDate = $tomorrow->toDateString();
        $tomorrowDayOfWeek = $tomorrow->dayOfWeek;

        DriverSchedule::create([
            'driver_id' => $this->driver1->id,
            'day_of_week' => $tomorrowDayOfWeek,
            'route_id' => $this->routeSalahAlDin->id,
            'start_time' => '07:30',
            'end_time' => '14:30',
            'is_day_off' => false,
        ]);

        // Search within working window (10:00): should match
        $resInWindow = $this->getJson("/api/rides/active-vehicles?date={$tomorrowDate}&time=10:00");
        $resInWindow->assertStatus(200);
        $vehiclesIn = $resInWindow->json('vehicles');
        $this->assertCount(1, collect($vehiclesIn)->where('driver_id', $this->driver1->id));

        // Search outside working window before start (06:00): should not match
        $resEarly = $this->getJson("/api/rides/active-vehicles?date={$tomorrowDate}&time=06:00");
        $resEarly->assertStatus(200);
        $vehiclesEarly = $resEarly->json('vehicles');
        $this->assertCount(0, collect($vehiclesEarly)->where('driver_id', $this->driver1->id));

        // Search outside working window after end (16:00): should not match
        $resLate = $this->getJson("/api/rides/active-vehicles?date={$tomorrowDate}&time=16:00");
        $resLate->assertStatus(200);
        $vehiclesLate = $resLate->json('vehicles');
        $this->assertCount(0, collect($vehiclesLate)->where('driver_id', $this->driver1->id));
    }

    public function test_free_seats_isolated_by_travel_date(): void
    {
        $todayDate = Carbon::today()->toDateString();
        $tomorrowDate = Carbon::tomorrow()->toDateString();

        $rider = User::create([
            'name' => 'راكب تجريبي',
            'email' => 'rider-advance-seats@meshwar.local',
            'phone' => '0599999888',
            'role' => 'rider',
            'password' => bcrypt('password'),
        ]);

        // Book 3 seats for TODAY on routeSalahAlDin
        Booking::create([
            'route_id' => $this->routeSalahAlDin->id,
            'rider_id' => $rider->id,
            'seats' => 3,
            'pickup_stop' => 'دوار العودة',
            'dropoff_stop' => 'مستشفى الشفاء',
            'total_fare_ils' => 30.0,
            'status' => 'confirmed',
            'travel_date' => $todayDate,
        ]);

        // Book 5 seats for TOMORROW on routeSalahAlDin
        Booking::create([
            'route_id' => $this->routeSalahAlDin->id,
            'rider_id' => $rider->id,
            'seats' => 5,
            'pickup_stop' => 'دوار العودة',
            'dropoff_stop' => 'مستشفى الشفاء',
            'total_fare_ils' => 50.0,
            'status' => 'confirmed',
            'travel_date' => $tomorrowDate,
        ]);

        // Today query: 7 capacity - 3 booked = 4 free seats
        $todayRes = $this->getJson("/api/rides/active-vehicles?date={$todayDate}");
        $todayRes->assertStatus(200);
        $d1Today = collect($todayRes->json('vehicles'))->firstWhere('driver_id', $this->driver1->id);
        $this->assertEquals(4, $d1Today['free_seats']);

        // Tomorrow query: 7 capacity - 5 booked = 2 free seats
        $tomorrowRes = $this->getJson("/api/rides/active-vehicles?date={$tomorrowDate}");
        $tomorrowRes->assertStatus(200);
        $d1Tomorrow = collect($tomorrowRes->json('vehicles'))->firstWhere('driver_id', $this->driver1->id);
        $this->assertEquals(2, $d1Tomorrow['free_seats']);
    }
}
