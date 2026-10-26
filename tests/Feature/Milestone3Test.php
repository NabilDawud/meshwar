<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Milestone3Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guest can access rider home screen without authentication.
     */
    public function test_guest_can_access_rider_home_map_screen(): void
    {
        $responseHome = $this->get('/');
        $responseHome->assertStatus(200)
            ->assertSee('بحث في رحلات ومركبات غزة النشطة')
            ->assertSee('rider-map');

        $responseRides = $this->get('/rides');
        $responseRides->assertStatus(200)
            ->assertSee('rider-map');
    }

    /**
     * Test /api/rides/active-vehicles returns active vehicle details and stays strictly under the 10 KB byte budget.
     */
    public function test_active_vehicles_endpoint_returns_json_within_byte_budget(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->getJson('/api/rides/active-vehicles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'ok',
                'count',
                'vehicles' => [
                    '*' => [
                        'vehicle_id',
                        'driver_name',
                        'vehicle_type',
                        'vehicle_type_arabic',
                        'seat_count',
                        'free_seats',
                        'photo',
                        'route_name',
                        'start_point',
                        'first_trip_time',
                        'is_trip_active',
                        'last_latitude',
                        'last_longitude',
                        'last_landmark',
                        'last_position_at',
                        'position_age_minutes',
                        'position_age_arabic',
                        'stops',
                    ],
                ],
            ]);

        $vehicles = $response->json('vehicles');
        $this->assertNotEmpty($vehicles);

        $first = $vehicles[0];
        $this->assertEquals('ajala', $first['vehicle_type']);
        $this->assertEquals(10, $first['free_seats']);
        $this->assertNotEmpty($first['photo']);
        $this->assertEquals('دوار بني سهيلا', $first['last_landmark']);
        $this->assertMatchesRegularExpression('/(الآن|منذ)/', $first['position_age_arabic']);

        // Byte Budget Assertion (< 10 KB / 10240 bytes)
        $contentLength = strlen($response->getContent());
        $this->assertLessThan(10240, $contentLength, "Active vehicles payload must stay under 10 KB budget. Actual: {$contentLength} bytes");
    }

    /**
     * Test search filters by from and to landmarks.
     */
    public function test_search_filters_by_from_and_to_stops(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Matching query
        $matching = $this->getJson('/api/rides/active-vehicles?from=دوار العودة&to=ميدان فلسطين');
        $matching->assertStatus(200);
        $this->assertEquals(1, $matching->json('count'));

        // Non-matching query
        $nonMatching = $this->getJson('/api/rides/active-vehicles?from=بيت لاهيا');
        $nonMatching->assertStatus(200);
        $this->assertEquals(0, $nonMatching->json('count'));
    }

    /**
     * Test driver can broadcast GPS position during an active trip.
     */
    public function test_driver_can_broadcast_position_during_active_trip(): void
    {
        $this->seed(DatabaseSeeder::class);
        $driver = User::where('email', 'driver@meshwar.local')->first();

        $response = $this->actingAs($driver)->postJson('/api/driver/position', [
            'lat' => 31.4170,
            'lng' => 34.3510,
            'landmark' => 'دوار البركة - دير البلح',
            'is_trip_active' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'position' => [
                    'landmark' => 'دوار البركة - دير البلح',
                    'is_trip_active' => true,
                ],
            ]);

        $route = $driver->driverRoute->fresh();
        $this->assertEquals('دوار البركة - دير البلح', $route->last_landmark);
        $this->assertEquals(31.4170, (float) $route->last_latitude);
        $this->assertEquals(34.3510, (float) $route->last_longitude);
        $this->assertTrue((bool) $route->is_trip_active);
    }

    /**
     * Test vehicle under maintenance is hidden from public active transit search.
     */
    public function test_vehicle_under_maintenance_is_hidden_from_active_rides(): void
    {
        $this->seed(DatabaseSeeder::class);
        $driver = User::where('email', 'driver@meshwar.local')->first();

        // Put vehicle under maintenance
        $driver->vehicle->update(['status' => 'maintenance']);

        $response = $this->getJson('/api/rides/active-vehicles');
        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('count'));
    }

    /**
     * Test PWA service worker and manifest are accessible for offline tile caching.
     */
    public function test_service_worker_and_manifest_exist(): void
    {
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('manifest.json'));

        $swContent = file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString('tile.openstreetmap.org', $swContent);
        $this->assertStringContainsString('mishwar-map-tiles-v1', $swContent);
    }
}
