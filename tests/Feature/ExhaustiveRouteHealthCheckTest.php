<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ChatMessage;
use App\Models\DriverRoute;
use App\Models\SpecialOffer;
use App\Models\SpecialRequest;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExhaustiveRouteHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected User $driver;

    protected User $driver2;

    protected User $rider;

    protected Vehicle $vehicle;

    protected DriverRoute $route;

    protected DriverRoute $route2;

    protected Booking $booking;

    protected SpecialRequest $specialRequest;

    protected SpecialOffer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->driver = User::where('email', 'driver@meshwar.local')->first();
        $this->driver2 = User::where('email', 'driver2@meshwar.local')->first();
        $this->rider = User::where('email', 'rider@meshwar.local')->first();

        $this->vehicle = $this->driver->vehicle;
        $this->route = $this->driver->driverRoute ?? $this->driver->driverRoutes()->first();

        // Create a secondary route for driver
        $this->route2 = DriverRoute::create([
            'driver_id' => $this->driver->id,
            'name' => 'مسار تجريبي إضافي',
            'start_point' => 'دوار النجمة',
            'first_trip_time' => '08:00',
            'status' => 'on_route',
            'is_active' => false,
            'is_two_way' => true,
            'stops' => [
                ['order' => 1, 'name' => 'دوار النجمة', 'lat' => 31.2825, 'lng' => 34.2514, 'fare_ils' => 0],
                ['order' => 2, 'name' => 'خانيونس البلد', 'lat' => 31.3456, 'lng' => 34.3050, 'fare_ils' => 5],
            ],
        ]);

        // Create a booking
        $this->booking = Booking::create([
            'rider_id' => $this->rider->id,
            'route_id' => $this->route->id,
            'travel_date' => today()->toDateString(),
            'seats' => 2,
            'pickup_stop' => $this->route->start_point,
            'dropoff_stop' => 'غزة الرمال',
            'fare_ils' => 10.0,
            'status' => 'confirmed',
        ]);

        // Create special request and offer
        $this->specialRequest = SpecialRequest::create([
            'rider_id' => $this->rider->id,
            'origin_name' => 'خانيونس',
            'destination_name' => 'غزة الرمال',
            'departure_date' => today()->addDay()->toDateString(),
            'departure_window' => '08:00 - 10:00',
            'seats_needed' => 4,
            'notes' => 'مشوار عائلي',
            'status' => 'open',
        ]);

        $this->offer = SpecialOffer::create([
            'request_id' => $this->specialRequest->id,
            'driver_id' => $this->driver->id,
            'price_ils' => 70,
            'status' => 'pending',
            'notes' => 'مركبة مكيفة جاهزة',
        ]);

        ChatMessage::create([
            'offer_id' => $this->offer->id,
            'sender_id' => $this->driver->id,
            'message' => 'السلام عليكم، العرض متاح وجاهز للتنفيذ',
        ]);
    }

    /**
     * Verify all public guest routes return 200 without authentication.
     */
    public function test_all_public_guest_routes_work(): void
    {
        $publicRoutes = [
            '/' => 200,
            '/rides' => 200,
            '/plan' => 200,
            '/login' => 200,
            '/register' => 200,
            '/forgot-password' => 200,
            '/reset-password/test-token' => 200,
            '/two-factor-challenge' => 302, // Redirects to /login if no active 2FA login challenge in session
            '/up' => 200,
            '/api/health' => 200,
            '/api/gazetteer' => 200,
            '/api/rides/active-vehicles' => 200,
            '/api/rides/map-pins' => 200,
            '/api/rides/plan?from=رفح&to=غزة' => 200,
            '/api/routes/graph' => 200,
        ];

        foreach ($publicRoutes as $uri => $expectedStatus) {
            $response = $this->get($uri);
            $this->assertNotEquals(500, $response->getStatusCode(), "Public route {$uri} failed with 500 error!");
            $response->assertStatus($expectedStatus);
        }
    }

    /**
     * Verify all rider web screens and APIs work seamlessly when authenticated as a rider.
     */
    public function test_all_rider_routes_work(): void
    {
        $this->actingAs($this->rider);

        $riderWebViews = [
            '/',
            '/rides',
            '/plan',
            '/bookings',
            '/requests',
            "/requests/{$this->specialRequest->id}",
            "/chat/{$this->offer->id}",
            '/trips',
            '/profile',
            '/user/confirm-password',
        ];

        foreach ($riderWebViews as $uri) {
            $response = $this->get($uri);
            $this->assertNotEquals(500, $response->getStatusCode(), "Rider view {$uri} crashed with 500!");
            $response->assertStatus(200);
        }

        $riderApis = [
            '/api/rider/bookings',
            '/api/rider/history',
            '/api/requests',
            "/api/requests/{$this->specialRequest->id}",
            "/api/chat/{$this->offer->id}",
            '/api/notifications',
        ];

        foreach ($riderApis as $uri) {
            $response = $this->getJson($uri);
            $this->assertNotEquals(500, $response->getStatusCode(), "Rider API {$uri} crashed with 500!");
            $response->assertStatus(200);
        }
    }

    /**
     * Verify all driver web screens and APIs work seamlessly when authenticated as a driver.
     */
    public function test_all_driver_routes_work(): void
    {
        $this->actingAs($this->driver);

        $driverWebViews = [
            '/',
            '/rides',
            '/plan',
            '/driver',
            '/driver/schedule',
            '/driver/vehicle',
            '/driver/route',
            "/driver/route?route_id={$this->route2->id}",
            '/driver/manifest',
            '/driver/offers',
            "/chat/{$this->offer->id}",
            '/driver/history',
            '/profile',
        ];

        foreach ($driverWebViews as $uri) {
            $response = $this->get($uri);
            $this->assertNotEquals(500, $response->getStatusCode(), "Driver view {$uri} crashed with 500!");
            $response->assertStatus(200);
        }

        $driverApis = [
            '/api/driver/vehicle',
            '/api/driver/routes',
            '/api/driver/route',
            '/api/driver/schedules',
            '/api/driver/manifest',
            '/api/driver/history',
            '/api/requests',
            "/api/requests/{$this->specialRequest->id}",
            "/api/chat/{$this->offer->id}",
            '/api/notifications',
        ];

        foreach ($driverApis as $uri) {
            $response = $this->getJson($uri);
            $this->assertNotEquals(500, $response->getStatusCode(), "Driver API {$uri} crashed with 500!");
            $response->assertStatus(200);
        }
    }

    /**
     * Verify driver redirects when accessing rider-specific web URLs.
     */
    public function test_driver_redirects_from_rider_views(): void
    {
        $this->actingAs($this->driver);

        $redirectMap = [
            '/bookings' => route('driver.manifest'),
            '/requests' => route('driver.offers'),
            "/requests/{$this->specialRequest->id}" => route('driver.offers'),
            '/trips' => route('driver.history'),
        ];

        foreach ($redirectMap as $uri => $expectedTarget) {
            $response = $this->get($uri);
            $this->assertNotEquals(500, $response->getStatusCode(), "Route {$uri} crashed with 500!");
            $response->assertRedirect($expectedTarget);
        }
    }

    /**
     * Verify rider is blocked from driver-only routes with 403.
     */
    public function test_rider_is_strictly_blocked_from_driver_views_and_apis(): void
    {
        $this->actingAs($this->rider);

        $blockedWeb = [
            '/driver',
            '/driver/schedule',
            '/driver/vehicle',
            '/driver/route',
            '/driver/manifest',
            '/driver/offers',
            '/driver/history',
        ];

        foreach ($blockedWeb as $uri) {
            $response = $this->get($uri);
            $this->assertNotEquals(500, $response->getStatusCode(), "Blocked view {$uri} crashed with 500 instead of 403!");
            $response->assertStatus(403);
        }

        $blockedApis = [
            '/api/driver/vehicle',
            '/api/driver/routes',
            '/api/driver/route',
            '/api/driver/schedules',
            '/api/driver/manifest',
            '/api/driver/history',
        ];

        foreach ($blockedApis as $uri) {
            $response = $this->getJson($uri);
            $this->assertNotEquals(500, $response->getStatusCode(), "Blocked API {$uri} crashed with 500 instead of 403!");
            $response->assertStatus(403);
        }
    }

    /**
     * Verify role choice redirection flow for unauthenticated guests,
     * new users without role, and existing users with assigned roles.
     */
    public function test_role_choice_flow_and_redirections(): void
    {
        // 1. Guest is redirected to /login
        $guestRes = $this->get('/auth/role-choice');
        $guestRes->assertRedirect(route('login'));

        // 2. User without role gets 200 view
        $newUser = User::factory()->create([
            'role' => null,
            'phone' => null,
        ]);
        $res = $this->actingAs($newUser)->get('/auth/role-choice');
        $res->assertStatus(200);

        // 3. Submitting role choice updates role and redirects
        $storeRes = $this->actingAs($newUser)->post('/auth/role-choice', [
            'role' => 'rider',
            'phone' => '0599123456',
        ]);
        $storeRes->assertRedirect(route('rider.home'));
        $this->assertEquals('rider', $newUser->fresh()->role);

        // 4. Rider visiting /auth/role-choice is redirected to rider.home
        $riderRes = $this->actingAs($this->rider)->get('/auth/role-choice');
        $riderRes->assertRedirect(route('rider.home'));

        // 5. Driver visiting /auth/role-choice is redirected to driver.dashboard
        $driverRes = $this->actingAs($this->driver)->get('/auth/role-choice');
        $driverRes->assertRedirect(route('driver.dashboard'));
    }

    /**
     * Verify all primary API action/mutation endpoints work without 500 crashes.
     */
    public function test_core_api_mutations_work(): void
    {
        // Driver updates route status
        $this->actingAs($this->driver);
        $res = $this->postJson('/api/driver/route/status', ['status' => 'off_route']);
        $res->assertStatus(200);

        // Driver updates route direction
        $res = $this->postJson('/api/driver/route/direction', ['direction' => 'return']);
        $res->assertStatus(200);

        // Driver confirms booking
        $res = $this->postJson("/api/driver/bookings/{$this->booking->id}/confirm");
        $res->assertStatus(200);

        // Driver posts chat message
        $res = $this->postJson("/api/chat/{$this->offer->id}", ['message' => 'مرحباً، أؤكد الرحلة']);
        $res->assertStatus(201);

        // Switch to rider
        $this->actingAs($this->rider);

        // Rider reads notifications
        $res = $this->postJson('/api/notifications/read-all');
        $res->assertStatus(200);

        // Rider books a trip
        $res = $this->postJson('/api/bookings', [
            'route_id' => $this->route->id,
            'travel_date' => today()->toDateString(),
            'seats' => 1,
            'pickup_stop' => $this->route->start_point,
            'dropoff_stop' => 'غزة الرمال',
        ]);
        $res->assertStatus(201);
    }
}
