<?php

namespace Tests\Feature;

use App\Models\DriverRoute;
use App\Models\SpecialRequest;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleRedirectionAndSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected User $driver;

    protected User $rider;

    protected DriverRoute $route;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = User::factory()->create([
            'role' => 'driver',
            'email' => 'driver-separation@meshwar.local',
        ]);

        Vehicle::create([
            'driver_id' => $this->driver->id,
            'type' => 'car',
            'seat_count' => 4,
            'license_plate' => 'غزة - 7777',
            'status' => 'active',
        ]);

        $this->route = DriverRoute::create([
            'driver_id' => $this->driver->id,
            'name' => 'مسار تجريبي',
            'start_point' => 'موقف الجنوب',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'is_active' => true,
            'stops' => [
                ['order' => 1, 'name' => 'موقف الجنوب', 'lat' => 31.3456, 'lng' => 34.3182, 'fare_ils' => 0],
                ['order' => 2, 'name' => 'البلد', 'lat' => 31.3500, 'lng' => 34.3200, 'fare_ils' => 5],
            ],
        ]);

        $this->rider = User::factory()->create([
            'role' => 'rider',
            'email' => 'rider-separation@meshwar.local',
        ]);
    }

    public function test_driver_visiting_bookings_is_redirected_to_driver_manifest(): void
    {
        $response = $this->actingAs($this->driver)->get('/bookings');
        $response->assertRedirect(route('driver.manifest'));
    }

    public function test_driver_visiting_requests_is_redirected_to_driver_offers(): void
    {
        $response = $this->actingAs($this->driver)->get('/requests');
        $response->assertRedirect(route('driver.offers'));
    }

    public function test_driver_visiting_request_detail_is_redirected_to_driver_offers(): void
    {
        $specialRequest = SpecialRequest::create([
            'rider_id' => $this->rider->id,
            'origin_name' => 'غزة',
            'destination_name' => 'خانيونس',
            'departure_date' => today()->addDay()->toDateString(),
            'departure_window' => '08:00 - 10:00',
            'seats_needed' => 2,
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->driver)->get("/requests/{$specialRequest->id}");
        $response->assertRedirect(route('driver.offers'));
    }

    public function test_driver_visiting_trips_is_redirected_to_driver_history(): void
    {
        $response = $this->actingAs($this->driver)->get('/trips');
        $response->assertRedirect(route('driver.history'));
    }

    public function test_driver_calling_rider_bookings_api_receives_403(): void
    {
        $response = $this->actingAs($this->driver)->getJson('/api/rider/bookings');
        $response->assertStatus(403)
            ->assertJson(['error' => 'حساب الراكب مطلوب']);
    }

    public function test_driver_calling_create_request_api_receives_403(): void
    {
        $payload = [
            'origin_name' => 'غزة',
            'destination_name' => 'رفح',
            'departure_date' => today()->addDay()->toDateString(),
            'departure_window' => '09:00 - 11:00',
            'seats_needed' => 1,
        ];

        $response = $this->actingAs($this->driver)->postJson('/api/requests', $payload);
        $response->assertStatus(403);
    }

    public function test_driver_calling_rider_history_api_receives_403(): void
    {
        $response = $this->actingAs($this->driver)->getJson('/api/rider/history');
        $response->assertStatus(403)
            ->assertJson(['error' => 'حساب الراكب مطلوب']);
    }

    public function test_rider_can_access_bookings_requests_and_trips(): void
    {
        $this->actingAs($this->rider)->get('/bookings')->assertStatus(200);
        $this->actingAs($this->rider)->get('/requests')->assertStatus(200);
        $this->actingAs($this->rider)->get('/trips')->assertStatus(200);
    }
}
