<?php

namespace Tests\Feature;

use App\Models\DriverRoute;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Milestone2Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Test /api/gazetteer endpoint returns real OpenStreetMap landmark nodes within Gaza Strip bounds.
     */
    public function test_gazetteer_endpoint_returns_valid_osm_landmarks(): void
    {
        $response = $this->getJson('/api/gazetteer');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'ok',
                'count',
                'landmarks' => [
                    '*' => ['id', 'name_ar', 'name_en', 'corridor', 'municipality', 'lat', 'lng', 'type'],
                ],
            ]);

        $landmarks = $response->json('landmarks');
        $this->assertGreaterThan(10, count($landmarks));

        // Verify bounding box coordinates (Gaza Strip)
        foreach ($landmarks as $landmark) {
            $this->assertGreaterThanOrEqual(31.2, $landmark['lat']);
            $this->assertLessThanOrEqual(31.6, $landmark['lat']);
            $this->assertGreaterThanOrEqual(34.2, $landmark['lng']);
            $this->assertLessThanOrEqual(34.6, $landmark['lng']);
        }
    }

    /**
     * Test database seed creates one vehicle with 10 seats, photo, and a real route.
     */
    public function test_database_seed_creates_10_seat_vehicle_and_real_route(): void
    {
        $this->seed(DatabaseSeeder::class);

        $driver = User::where('email', 'driver@meshwar.local')->first();
        $this->assertNotNull($driver);
        $this->assertEquals('driver', $driver->role);

        // Verify 10-seat vehicle with photo
        $vehicle = $driver->vehicle;
        $this->assertNotNull($vehicle);
        $this->assertEquals('ajala', $vehicle->type);
        $this->assertEquals(10, $vehicle->seat_count);
        $this->assertNotEmpty($vehicle->photos);

        // Verify real route with ordered stops and fares
        $route = $driver->driverRoute;
        $this->assertNotNull($route);
        $this->assertEquals('on_route', $route->status);
        $this->assertCount(5, $route->stops);
        $this->assertEquals('دوار العودة - وسط رفح', $route->stops[0]['name']);
        $this->assertEquals(0, $route->stops[0]['fare_ils']);
        $this->assertEquals(20, $route->stops[4]['fare_ils']);
    }

    /**
     * Test driver can register and update single vehicle (1:1 constraint).
     */
    public function test_driver_can_register_and_update_vehicle_enforcing_single_vehicle_rule(): void
    {
        $driver = User::factory()->create([
            'role' => 'driver',
            'phone' => '0599111222',
        ]);

        // 1. Initial vehicle registration
        $response = $this->actingAs($driver)->postJson('/api/driver/vehicle', [
            'type' => 'ajala',
            'seat_count' => 10,
            'license_plate' => 'غزة - 1234',
            'status' => 'active',
            'photos' => ['/images/sample-ajala.svg'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'vehicle' => [
                    'type' => 'ajala',
                    'seat_count' => 10,
                ],
            ]);

        $this->assertEquals(1, Vehicle::where('driver_id', $driver->id)->count());

        // 2. Update existing vehicle (does not duplicate due to 1:1 constraint)
        $updateResponse = $this->actingAs($driver)->postJson('/api/driver/vehicle', [
            'type' => 'bus',
            'seat_count' => 14,
            'license_plate' => 'غزة - 5678',
            'status' => 'maintenance',
            'photos' => ['/images/sample-ajala.svg'],
        ]);

        $updateResponse->assertStatus(200);
        $this->assertEquals(1, Vehicle::where('driver_id', $driver->id)->count());
        $this->assertEquals('bus', $driver->fresh()->vehicle->type);
        $this->assertEquals(14, $driver->fresh()->vehicle->seat_count);
        $this->assertEquals('maintenance', $driver->fresh()->vehicle->status);
    }

    /**
     * Test vehicle registration validation rules.
     */
    public function test_vehicle_registration_validates_type_and_seat_count(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        $response = $this->actingAs($driver)->postJson('/api/driver/vehicle', [
            'type' => 'invalid_type',
            'seat_count' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'seat_count']);
    }

    /**
     * Test driver can save route with ordered stops and leg fares.
     */
    public function test_driver_can_save_route_with_ordered_stops_and_fares(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        $payload = [
            'name' => 'رفح <-> غزة (صلاح الدين)',
            'start_point' => 'دوار العودة',
            'first_trip_time' => '06:30',
            'stops' => [
                [
                    'order' => 1,
                    'name' => 'دوار العودة',
                    'lat' => 31.2828,
                    'lng' => 34.2536,
                    'fare_ils' => 0,
                ],
                [
                    'order' => 2,
                    'name' => 'دوار بني سهيلا',
                    'lat' => 31.3456,
                    'lng' => 34.3182,
                    'fare_ils' => 5,
                ],
            ],
        ];

        $response = $this->actingAs($driver)->postJson('/api/driver/route', $payload);

        $response->assertStatus(200)
            ->assertJson(['ok' => true]);

        $route = DriverRoute::where('driver_id', $driver->id)->first();
        $this->assertNotNull($route);
        $this->assertEquals('رفح <-> غزة (صلاح الدين)', $route->name);
        $this->assertCount(2, $route->stops);
        $this->assertEquals(5, $route->stops[1]['fare_ils']);
    }

    /**
     * Test driver can toggle operational state machine status.
     */
    public function test_driver_can_toggle_operational_status(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'مسار تجريبي',
            'start_point' => 'نقطة 1',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'stops' => [
                ['order' => 1, 'name' => 'نقطة 1', 'lat' => 31.3, 'lng' => 34.3],
                ['order' => 2, 'name' => 'نقطة 2', 'lat' => 31.4, 'lng' => 34.4],
            ],
        ]);

        $response = $this->actingAs($driver)->postJson('/api/driver/route/status', [
            'status' => 'temporary_route',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'status' => 'temporary_route',
                'status_arabic' => 'مسار مؤقت',
            ]);

        $this->assertEquals('temporary_route', $driver->driverRoute->fresh()->status);
    }

    /**
     * Test driver dashboard screen renders vehicle and route state.
     */
    public function test_driver_dashboard_renders_vehicle_and_route(): void
    {
        $this->seed(DatabaseSeeder::class);
        $driver = User::where('email', 'driver@meshwar.local')->first();

        $response = $this->actingAs($driver)->get('/driver');

        $response->assertStatus(200);
        $response->assertSee('عجلة');
        $response->assertSee('10 ركاب');
        $response->assertSee('رفح <-> غزة (عبر طريق صلاح الدين)');
        $response->assertSee('دوار العودة');
    }
}
