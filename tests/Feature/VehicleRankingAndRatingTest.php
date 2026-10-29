<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\DriverRoute;
use App\Models\TripRating;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleRankingAndRatingTest extends TestCase
{
    use RefreshDatabase;

    protected User $rider;

    protected User $driverSenior5Star;

    protected User $driverJunior5Star;

    protected User $driverLowRated;

    protected User $driverNewcomer;

    protected User $driverReturnLeg;

    protected Vehicle $vehicleSenior5Star;

    protected Vehicle $vehicleJunior5Star;

    protected Vehicle $vehicleLowRated;

    protected Vehicle $vehicleNewcomer;

    protected Vehicle $vehicleReturnLeg;

    protected function setUp(): void
    {
        parent::setUp();

        $corridorStops = [
            ['order' => 1, 'name' => 'دوار العودة', 'lat' => 31.2800, 'lng' => 34.2500, 'fare_ils' => 0.0],
            ['order' => 2, 'name' => 'مفترق خربة العدس', 'lat' => 31.3000, 'lng' => 34.2700, 'fare_ils' => 3.0],
            ['order' => 3, 'name' => 'دوار بني سهيلا', 'lat' => 31.3456, 'lng' => 34.3182, 'fare_ils' => 5.0],
            ['order' => 4, 'name' => 'مستشفى الشفاء', 'lat' => 31.5200, 'lng' => 34.4400, 'fare_ils' => 8.0],
        ];

        $this->rider = User::create([
            'name' => 'الراكب المقيّم',
            'email' => 'rider-rank@meshwar.local',
            'phone' => '0599000111',
            'role' => 'rider',
            'password' => bcrypt('password'),
        ]);

        // 1. Senior 5-Star Driver (Created First -> Lowest ID)
        $this->driverSenior5Star = User::create([
            'name' => 'سائق قديم 5 نجوم',
            'email' => 'driver-senior-5star@meshwar.local',
            'phone' => '0599000001',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);
        $this->vehicleSenior5Star = Vehicle::create([
            'driver_id' => $this->driverSenior5Star->id,
            'type' => 'bus',
            'seat_count' => 7,
            'license_plate' => '7-0001-99',
            'status' => 'active',
        ]);
        $route1 = DriverRoute::create([
            'driver_id' => $this->driverSenior5Star->id,
            'name' => 'رفح - غزة',
            'start_point' => 'دوار العودة',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'is_two_way' => true,
            'active_direction' => 'outbound',
            'stops' => $corridorStops,
        ]);
        // Add 5-star rating for driver 1
        $b1 = Booking::create([
            'rider_id' => $this->rider->id,
            'route_id' => $route1->id,
            'seats' => 1,
            'pickup_stop' => 'دوار العودة',
            'dropoff_stop' => 'مستشفى الشفاء',
            'status' => 'completed',
        ]);
        TripRating::create([
            'booking_id' => $b1->id,
            'driver_id' => $this->driverSenior5Star->id,
            'rider_id' => $this->rider->id,
            'rating' => 5,
            'comment' => 'سائق خبير وممتاز',
        ]);

        // 2. Junior 5-Star Driver (Created Later)
        $this->driverJunior5Star = User::create([
            'name' => 'سائق جديد 5 نجوم',
            'email' => 'driver-junior-5star@meshwar.local',
            'phone' => '0599000002',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);
        $this->vehicleJunior5Star = Vehicle::create([
            'driver_id' => $this->driverJunior5Star->id,
            'type' => 'bus',
            'seat_count' => 7,
            'license_plate' => '7-0002-99',
            'status' => 'active',
        ]);
        $route2 = DriverRoute::create([
            'driver_id' => $this->driverJunior5Star->id,
            'name' => 'رفح - غزة',
            'start_point' => 'دوار العودة',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'is_two_way' => true,
            'active_direction' => 'outbound',
            'stops' => $corridorStops,
        ]);
        $b2 = Booking::create([
            'rider_id' => $this->rider->id,
            'route_id' => $route2->id,
            'seats' => 1,
            'pickup_stop' => 'دوار العودة',
            'dropoff_stop' => 'مستشفى الشفاء',
            'status' => 'completed',
        ]);
        TripRating::create([
            'booking_id' => $b2->id,
            'driver_id' => $this->driverJunior5Star->id,
            'rider_id' => $this->rider->id,
            'rating' => 5,
            'comment' => 'سائق ممتاز',
        ]);

        // 3. Newcomer Driver (No ratings yet)
        $this->driverNewcomer = User::create([
            'name' => 'سائق انضم حديثا',
            'email' => 'driver-newcomer@meshwar.local',
            'phone' => '0599000003',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);
        $this->vehicleNewcomer = Vehicle::create([
            'driver_id' => $this->driverNewcomer->id,
            'type' => 'bus',
            'seat_count' => 7,
            'license_plate' => '7-0003-99',
            'status' => 'active',
        ]);
        DriverRoute::create([
            'driver_id' => $this->driverNewcomer->id,
            'name' => 'رفح - غزة',
            'start_point' => 'دوار العودة',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'is_two_way' => true,
            'active_direction' => 'outbound',
            'stops' => $corridorStops,
        ]);

        // 4. Low-Rated Driver (3-star)
        $this->driverLowRated = User::create([
            'name' => 'سائق تقييم منخفض',
            'email' => 'driver-low-rated@meshwar.local',
            'phone' => '0599000004',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);
        $this->vehicleLowRated = Vehicle::create([
            'driver_id' => $this->driverLowRated->id,
            'type' => 'bus',
            'seat_count' => 7,
            'license_plate' => '7-0004-99',
            'status' => 'active',
        ]);
        $route4 = DriverRoute::create([
            'driver_id' => $this->driverLowRated->id,
            'name' => 'رفح - غزة',
            'start_point' => 'دوار العودة',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'is_two_way' => true,
            'active_direction' => 'outbound',
            'stops' => $corridorStops,
        ]);
        $b4 = Booking::create([
            'rider_id' => $this->rider->id,
            'route_id' => $route4->id,
            'seats' => 1,
            'pickup_stop' => 'دوار العودة',
            'dropoff_stop' => 'مستشفى الشفاء',
            'status' => 'completed',
        ]);
        TripRating::create([
            'booking_id' => $b4->id,
            'driver_id' => $this->driverLowRated->id,
            'rider_id' => $this->rider->id,
            'rating' => 2,
            'comment' => 'تأخير في الانطلاق',
        ]);

        // 5. Driver on Return Leg (5-star, but active_direction = 'return')
        $this->driverReturnLeg = User::create([
            'name' => 'سائق خط العودة',
            'email' => 'driver-return-leg@meshwar.local',
            'phone' => '0599000005',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);
        $this->vehicleReturnLeg = Vehicle::create([
            'driver_id' => $this->driverReturnLeg->id,
            'type' => 'bus',
            'seat_count' => 7,
            'license_plate' => '7-0005-99',
            'status' => 'active',
        ]);
        $route5 = DriverRoute::create([
            'driver_id' => $this->driverReturnLeg->id,
            'name' => 'رفح - غزة',
            'start_point' => 'دوار العودة',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'is_two_way' => true,
            'active_direction' => 'return',
            'stops' => $corridorStops,
        ]);
        $b5 = Booking::create([
            'rider_id' => $this->rider->id,
            'route_id' => $route5->id,
            'seats' => 1,
            'pickup_stop' => 'دوار العودة',
            'dropoff_stop' => 'مستشفى الشفاء',
            'status' => 'completed',
        ]);
        TripRating::create([
            'booking_id' => $b5->id,
            'driver_id' => $this->driverReturnLeg->id,
            'rider_id' => $this->rider->id,
            'rating' => 5,
        ]);
    }

    public function test_api_returns_rating_and_ratings_count_for_each_vehicle(): void
    {
        $response = $this->getJson('/api/rides/active-vehicles');
        $response->assertStatus(200);

        $vehicles = $response->json('vehicles');
        $senior = collect($vehicles)->firstWhere('vehicle_id', $this->vehicleSenior5Star->id);
        $this->assertNotNull($senior);
        $this->assertEquals(5.0, (float) $senior['rating']);
        $this->assertEquals(1, (int) $senior['ratings_count']);

        $newcomer = collect($vehicles)->firstWhere('vehicle_id', $this->vehicleNewcomer->id);
        $this->assertNotNull($newcomer);
        $this->assertEquals(0.0, (float) $newcomer['rating']);
        $this->assertEquals(0, (int) $newcomer['ratings_count']);
    }

    public function test_main_screen_prioritizes_senior_drivers_among_top_rated(): void
    {
        // When browsing without directional search, Senior 5-Star driver must appear before Junior 5-Star driver
        $response = $this->getJson('/api/rides/active-vehicles');
        $response->assertStatus(200);

        $vehicles = $response->json('vehicles');
        $seniorIndex = collect($vehicles)->search(fn ($v) => $v['vehicle_id'] === $this->vehicleSenior5Star->id);
        $juniorIndex = collect($vehicles)->search(fn ($v) => $v['vehicle_id'] === $this->vehicleJunior5Star->id);
        $lowRatedIndex = collect($vehicles)->search(fn ($v) => $v['vehicle_id'] === $this->vehicleLowRated->id);

        $this->assertLessThan($juniorIndex, $seniorIndex, 'Older 5-star driver must rank before newer 5-star driver (Seniority priority)');
        $this->assertLessThan($lowRatedIndex, $seniorIndex, 'High-rated driver must rank before low-rated driver');
        $this->assertLessThan($lowRatedIndex, $juniorIndex, 'Junior 5-star driver must rank before low-rated driver');
    }

    public function test_directional_search_prioritizes_active_direction_over_return_leg(): void
    {
        // Searching outbound (خربة العدس -> بني سهيلا)
        $response = $this->getJson('/api/rides/active-vehicles?from=مفترق خربة العدس&to=دوار بني سهيلا');
        $response->assertStatus(200);

        $vehicles = $response->json('vehicles');
        $seniorActiveIndex = collect($vehicles)->search(fn ($v) => $v['vehicle_id'] === $this->vehicleSenior5Star->id);
        $returnLegIndex = collect($vehicles)->search(fn ($v) => $v['vehicle_id'] === $this->vehicleReturnLeg->id);

        $this->assertLessThan($returnLegIndex, $seniorActiveIndex, 'Active direction vehicle must rank before return-leg vehicle');
    }
}
