<?php

namespace Tests\Feature;

use App\Models\DriverRoute;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectionalSearchFilteringTest extends TestCase
{
    use RefreshDatabase;

    protected DriverRoute $routeOutbound;
    protected DriverRoute $routeReturn;
    protected DriverRoute $routeOneWay;
    protected Vehicle $vehicleOutbound;
    protected Vehicle $vehicleReturn;
    protected Vehicle $vehicleOneWay;

    protected function setUp(): void
    {
        parent::setUp();

        // Standard stops: Rafah (0) -> Khirbat Al-Adas (3) -> Bani Suheila (5) -> Gaza Shifa (8)
        $corridorStops = [
            ['order' => 1, 'name' => 'دوار العودة', 'lat' => 31.2800, 'lng' => 34.2500, 'fare_ils' => 0.0],
            ['order' => 2, 'name' => 'مفترق خربة العدس', 'lat' => 31.3000, 'lng' => 34.2700, 'fare_ils' => 3.0],
            ['order' => 3, 'name' => 'دوار بني سهيلا', 'lat' => 31.3456, 'lng' => 34.3182, 'fare_ils' => 5.0],
            ['order' => 4, 'name' => 'مستشفى الشفاء', 'lat' => 31.5200, 'lng' => 34.4400, 'fare_ils' => 8.0],
        ];

        // Driver 1: Outbound active
        $driver1 = User::create([
            'name' => 'سائق الذهاب',
            'email' => 'driver-outbound@meshwar.local',
            'phone' => '0599100001',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);
        $this->vehicleOutbound = Vehicle::create([
            'driver_id' => $driver1->id,
            'type' => 'bus',
            'seat_count' => 7,
            'license_plate' => '7-1001-99',
            'status' => 'active',
        ]);
        $this->routeOutbound = DriverRoute::create([
            'driver_id' => $driver1->id,
            'name' => 'رفح - غزة',
            'start_point' => 'دوار العودة',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'is_two_way' => true,
            'active_direction' => 'outbound',
            'stops' => $corridorStops,
        ]);

        // Driver 2: Return active
        $driver2 = User::create([
            'name' => 'سائق العودة',
            'email' => 'driver-return@meshwar.local',
            'phone' => '0599100002',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);
        $this->vehicleReturn = Vehicle::create([
            'driver_id' => $driver2->id,
            'type' => 'bus',
            'seat_count' => 7,
            'license_plate' => '7-1002-99',
            'status' => 'active',
        ]);
        $this->routeReturn = DriverRoute::create([
            'driver_id' => $driver2->id,
            'name' => 'رفح - غزة',
            'start_point' => 'دوار العودة',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'is_two_way' => true,
            'active_direction' => 'return',
            'stops' => $corridorStops,
        ]);

        // Driver 3: One-way only (outbound only, cannot do return)
        $driver3 = User::create([
            'name' => 'سائق اتجاه واحد',
            'email' => 'driver-oneway@meshwar.local',
            'phone' => '0599100003',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);
        $this->vehicleOneWay = Vehicle::create([
            'driver_id' => $driver3->id,
            'type' => 'bus',
            'seat_count' => 7,
            'license_plate' => '7-1003-99',
            'status' => 'active',
        ]);
        $this->routeOneWay = DriverRoute::create([
            'driver_id' => $driver3->id,
            'name' => 'رفح - غزة اتجاه واحد',
            'start_point' => 'دوار العودة',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'is_two_way' => false,
            'active_direction' => 'outbound',
            'stops' => $corridorStops,
        ]);
    }

    public function test_outbound_search_prioritizes_outbound_vehicle_and_labels_return_leg(): void
    {
        // Search B -> C (خربة العدس to بني سهيلا, outbound vector)
        $response = $this->getJson('/api/rides/active-vehicles?from=مفترق خربة العدس&to=دوار بني سهيلا');
        $response->assertStatus(200);

        $vehicles = $response->json('vehicles');
        $this->assertNotEmpty($vehicles);

        // First vehicle must have active direction match
        $this->assertEquals('active', $vehicles[0]['direction_match']);
        $this->assertEquals('متجه لوجهتك الآن', $vehicles[0]['direction_match_arabic']);
        $this->assertEquals(2.0, (float) $vehicles[0]['segment_fare_ils']);

        // Outbound vehicle must be active
        $outboundData = collect($vehicles)->firstWhere('vehicle_id', $this->vehicleOutbound->id);
        $this->assertNotNull($outboundData);
        $this->assertEquals('active', $outboundData['direction_match']);

        // Return vehicle must also be present (Option 2), but labeled return_leg and ranked after active
        $returnVehicleData = collect($vehicles)->firstWhere('vehicle_id', $this->vehicleReturn->id);
        $this->assertNotNull($returnVehicleData);
        $this->assertEquals('return_leg', $returnVehicleData['direction_match']);
        $this->assertEquals('على خط العودة', $returnVehicleData['direction_match_arabic']);
        $this->assertEquals(2.0, (float) $returnVehicleData['segment_fare_ils']);

        $outboundIdx = collect($vehicles)->search(fn ($v) => $v['vehicle_id'] === $this->vehicleOutbound->id);
        $returnIdx = collect($vehicles)->search(fn ($v) => $v['vehicle_id'] === $this->vehicleReturn->id);
        $this->assertGreaterThan($outboundIdx, $returnIdx, 'Active direction vehicle must rank before return-leg vehicle');
    }

    public function test_same_direction_only_filter_excludes_return_leg(): void
    {
        // Search B -> C with same_direction_only=1
        $response = $this->getJson('/api/rides/active-vehicles?from=مفترق خربة العدس&to=دوار بني سهيلا&same_direction_only=1');
        $response->assertStatus(200);

        $vehicles = $response->json('vehicles');
        $ids = collect($vehicles)->pluck('vehicle_id')->all();

        $this->assertContains($this->vehicleOutbound->id, $ids);
        $this->assertNotContains($this->vehicleReturn->id, $ids, 'Vehicles on return leg must be excluded when same_direction_only is true');
    }

    public function test_one_way_route_is_excluded_from_reverse_return_searches(): void
    {
        // Search C -> B (بني سهيلا to خربة العدس, reverse return vector)
        $response = $this->getJson('/api/rides/active-vehicles?from=دوار بني سهيلا&to=مفترق خربة العدس');
        $response->assertStatus(200);

        $vehicles = $response->json('vehicles');
        $ids = collect($vehicles)->pluck('vehicle_id')->all();

        // Driver 3 (one-way outbound) must NOT appear in reverse searches
        $this->assertNotContains($this->vehicleOneWay->id, $ids, 'One-way routes must not be returned for reverse trips');
        // Driver 2 (return active) should be the first active match
        $this->assertEquals($this->vehicleReturn->id, $vehicles[0]['vehicle_id']);
        $this->assertEquals('active', $vehicles[0]['direction_match']);
    }
}
