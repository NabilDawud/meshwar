<?php

namespace Tests\Feature;

use App\Models\DriverRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AiTransitAssistantService;
use App\Services\TripPlannerService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Milestone4Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a tiny fixed transit network for deterministic unit testing.
     *
     * Network topology:
     * - Route 1 (Salah al-Din South): Stop A (Rafah) -> Stop B (Khan Younis) -> Stop C (Netzarim Junction) [Fares: 0, 5, 15 ILS, 07:00]
     * - Route 2 (Al-Rashid Cross Link): Stop C (Netzarim Junction) -> Stop D (Gaza Seaport) [Fares: 0, 10 ILS, 07:30]
     * - Route 3 (Isolated North): Stop E (Beit Hanoun) -> Stop F (Beit Lahia) [Fares: 0, 5 ILS, 08:00]
     */
    protected function createTinyFixedNetwork()
    {
        $driver1 = new User(['id' => 101, 'name' => 'سائق الخط الأول']);
        $vehicle1 = new Vehicle(['type' => 'ajala', 'seat_count' => 10]);
        $driver1->setRelation('vehicle', $vehicle1);

        $route1 = new DriverRoute([
            'id' => 1,
            'name' => 'خط صلاح الدين الجنوبي',
            'start_point' => 'دوار العودة - رفح',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'stops' => [
                ['order' => 1, 'name' => 'دوار العودة - رفح', 'fare_ils' => 0, 'lat' => 31.2828, 'lng' => 34.2536],
                ['order' => 2, 'name' => 'دوار بني سهيلا', 'fare_ils' => 5, 'lat' => 31.3456, 'lng' => 34.3182],
                ['order' => 3, 'name' => 'مفترق الشهداء (نتساريم)', 'fare_ils' => 15, 'lat' => 31.4650, 'lng' => 34.4250],
            ],
        ]);
        $route1->id = 1;
        $route1->setRelation('driver', $driver1);

        $driver2 = new User(['id' => 102, 'name' => 'سائق الخط الثاني']);
        $vehicle2 = new Vehicle(['type' => 'car', 'seat_count' => 4]);
        $driver2->setRelation('vehicle', $vehicle2);

        $route2 = new DriverRoute([
            'id' => 2,
            'name' => 'خط الرشيد والساحل',
            'start_point' => 'مفترق الشهداء (نتساريم)',
            'first_trip_time' => '07:30',
            'status' => 'on_route',
            'stops' => [
                ['order' => 1, 'name' => 'مفترق الشهداء (نتساريم)', 'fare_ils' => 0, 'lat' => 31.4650, 'lng' => 34.4250],
                ['order' => 2, 'name' => 'ميناء غزة - شارع الرشيد', 'fare_ils' => 10, 'lat' => 31.5270, 'lng' => 34.4330],
            ],
        ]);
        $route2->id = 2;
        $route2->setRelation('driver', $driver2);

        $driver3 = new User(['id' => 103, 'name' => 'سائق معزول']);
        $vehicle3 = new Vehicle(['type' => 'bus', 'seat_count' => 20]);
        $driver3->setRelation('vehicle', $vehicle3);

        $route3 = new DriverRoute([
            'id' => 3,
            'name' => 'خط شمالي معزول',
            'start_point' => 'مدخل بيت حانون الرئيسي',
            'first_trip_time' => '08:00',
            'status' => 'on_route',
            'stops' => [
                ['order' => 1, 'name' => 'مدخل بيت حانون الرئيسي', 'fare_ils' => 0, 'lat' => 31.5420, 'lng' => 34.5360],
                ['order' => 2, 'name' => 'دوار بيت لاهيا العام', 'fare_ils' => 5, 'lat' => 31.5470, 'lng' => 34.4970],
            ],
        ]);
        $route3->id = 3;
        $route3->setRelation('driver', $driver3);

        return collect([$route1, $route2, $route3]);
    }

    /**
     * Test 1: Direct trip on tiny fixed network.
     */
    public function test_planner_finds_direct_trip_with_correct_fare(): void
    {
        $network = $this->createTinyFixedNetwork();
        $planner = new TripPlannerService;

        $result = $planner->plan('دوار العودة - رفح', 'دوار بني سهيلا', null, $network);

        $this->assertTrue($result['found']);
        $this->assertNotEmpty($result['itineraries']);

        $top = $result['itineraries'][0];
        $this->assertEquals('direct', $top['type']);
        $this->assertEquals(0, $top['transfers']);
        $this->assertEquals(5, $top['total_fare_ils']);
        $this->assertEquals('07:00', $top['departure_time']);
        $this->assertEquals('خط صلاح الدين الجنوبي', $top['route_name']);
    }

    /**
     * Test 2: Two-vehicle trip on tiny fixed network with wait time and total fare.
     */
    public function test_planner_finds_two_vehicle_trip_with_wait_and_total_fare(): void
    {
        $network = $this->createTinyFixedNetwork();
        $planner = new TripPlannerService;

        $result = $planner->plan('دوار العودة - رفح', 'ميناء غزة - شارع الرشيد', null, $network);

        $this->assertTrue($result['found']);
        $this->assertNotEmpty($result['itineraries']);

        $transferItin = $result['itineraries'][0];
        $this->assertEquals('one_transfer', $transferItin['type']);
        $this->assertEquals(1, $transferItin['transfers']);
        $this->assertEquals('مفترق الشهداء (نتساريم)', $transferItin['transfer_junction']);

        // Leg 1: 15 ILS, Leg 2: 10 ILS -> Total: 25 ILS
        $this->assertEquals(25, $transferItin['total_fare_ils']);
        $this->assertEquals(15, $transferItin['leg_1']['fare_ils']);
        $this->assertEquals(10, $transferItin['leg_2']['fare_ils']);

        // Wait time: 07:00 to 07:30 = 30 minutes
        $this->assertEquals(30, $transferItin['transfer_wait_minutes']);
        $this->assertStringContainsString('30', $transferItin['transfer_wait_arabic']);
    }

    /**
     * Test 3: "No route" between disconnected nodes on tiny fixed network.
     */
    public function test_planner_correctly_reports_no_route_when_nodes_disconnected(): void
    {
        $network = $this->createTinyFixedNetwork();
        $planner = new TripPlannerService;

        // From South (Rafah) to Isolated North (Beit Hanoun) with no connecting corridor
        $result = $planner->plan('دوار العودة - رفح', 'مدخل بيت حانون الرئيسي', null, $network);

        $this->assertFalse($result['found']);
        $this->assertEmpty($result['itineraries']);
        $this->assertStringContainsString('لا يوجد مسار متاح', $result['message']);
    }

    /**
     * Test 4: App is fully usable without ANTHROPIC_API_KEY.
     */
    public function test_planner_works_cleanly_with_no_ai_key_set(): void
    {
        putenv('ANTHROPIC_API_KEY=');
        $_ENV['ANTHROPIC_API_KEY'] = null;

        $network = $this->createTinyFixedNetwork();
        $planner = new TripPlannerService(new AiTransitAssistantService);

        $result = $planner->plan('دوار العودة - رفح', 'دوار بني سهيلا', null, $network);

        $this->assertTrue($result['found']);
        $this->assertFalse($result['ai_advice']['enabled']);
        $this->assertEquals('deterministic_fallback', $result['ai_advice']['provider']);
        $this->assertNotEmpty($result['ai_advice']['tip']);
        $this->assertStringContainsString('نصيحة مشوار', $result['ai_advice']['tip']);
    }

    /**
     * Test 5: Optional AI layer when ANTHROPIC_API_KEY is present.
     */
    public function test_optional_ai_layer_activates_when_key_provided(): void
    {
        putenv('ANTHROPIC_API_KEY=mock-test-key');
        $_ENV['ANTHROPIC_API_KEY'] = 'mock-test-key';

        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    ['text' => 'نصيحة ذكية: احرص على حجز مقعدك باكراً قبل ازدحام المفترق.'],
                ],
            ], 200),
        ]);

        $network = $this->createTinyFixedNetwork();
        $planner = new TripPlannerService(new AiTransitAssistantService);

        $result = $planner->plan('دوار العودة - رفح', 'دوار بني سهيلا', null, $network);

        $this->assertTrue($result['found']);
        $this->assertTrue($result['ai_advice']['enabled']);
        $this->assertEquals('anthropic_claude', $result['ai_advice']['provider']);
        $this->assertStringContainsString('نصيحة ذكية', $result['ai_advice']['tip']);

        // Clean up environment
        putenv('ANTHROPIC_API_KEY=');
        $_ENV['ANTHROPIC_API_KEY'] = null;
    }

    /**
     * Test 6: GET /api/rides/plan endpoint returns json with byte budget header.
     */
    public function test_api_plan_endpoint_returns_json_within_byte_budget(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->getJson('/api/rides/plan?from=دوار العودة&to=ميدان فلسطين');

        $response->assertStatus(200)
            ->assertJson(['ok' => true, 'found' => true])
            ->assertHeader('X-Payload-Bytes');

        $payloadBytes = (int) $response->headers->get('X-Payload-Bytes');
        $this->assertLessThan(10240, $payloadBytes, "Trip planner API payload must stay under 10 KB budget. Actual: {$payloadBytes} bytes");
    }

    /**
     * Test 7: GET /api/routes/graph returns lightweight graph for offline client-side caching.
     */
    public function test_api_routes_graph_returns_lightweight_network_for_offline_cache(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->getJson('/api/routes/graph');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'ok',
                'count',
                'routes' => [
                    '*' => ['id', 'name', 'departure_time', 'vehicle', 'stops'],
                ],
            ]);

        $payloadBytes = (int) $response->headers->get('X-Payload-Bytes');
        $this->assertLessThan(10240, $payloadBytes);
    }

    /**
     * Test 8: Trip planner web view renders.
     */
    public function test_trip_planner_web_view_renders(): void
    {
        $response = $this->get('/plan');

        $response->assertStatus(200)
            ->assertSee('مخطط الرحلات والتبديل')
            ->assertSee('offline-planner.js');
    }

    /**
     * Test 9: Artisan command meshwar:plan-trip outputs itinerary in terminal.
     */
    public function test_artisan_plan_trip_command_executes_successfully(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('meshwar:plan-trip', [
            'from' => 'دوار العودة',
            'to' => 'ميدان فلسطين',
        ])
            ->expectsOutputToContain('الخيار #1: رحلة مباشرة')
            ->expectsOutputToContain('إجمالي الأجرة التقديرية')
            ->assertExitCode(0);
    }
}
