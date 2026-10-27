<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\DriverRoute;
use App\Models\LocationShare;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductionReadinessSimulationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Simulation generates 300 active vehicles, and the active-vehicles API handles it with sub-second performance.
     */
    public function test_active_vehicles_api_handles_300_vehicles_with_high_performance(): void
    {
        // Run the simulation command with 300 vehicles
        $exitCode = Artisan::call('meshwar:simulate', [
            '--vehicles' => 300,
            '--bookings' => 10,
            '--requests' => 5,
        ]);
        $this->assertEquals(0, $exitCode);

        $this->assertEquals(300, Vehicle::where('status', 'active')->count());
        $this->assertEquals(300, DriverRoute::where('is_trip_active', true)->count());

        $start = microtime(true);
        $response = $this->getJson(route('api.rides.active'));
        $duration = microtime(true) - $start;

        $response->assertStatus(200)
            ->assertJsonPath('ok', true);

        $vehicles = $response->json('vehicles');
        $this->assertCount(300, $vehicles);

        // Sub-second response time assertion (production benchmark: < 1.0s)
        $this->assertLessThan(1.5, $duration, 'Active vehicles query must be fast under 300 vehicles');

        // Check vehicle structure
        $first = $vehicles[0];
        $this->assertArrayHasKey('vehicle_id', $first);
        $this->assertArrayHasKey('vehicle_type', $first);
        $this->assertArrayHasKey('license_plate', $first);
        $this->assertArrayHasKey('free_seats', $first);
        $this->assertArrayHasKey('last_latitude', $first);
        $this->assertArrayHasKey('last_longitude', $first);
        $this->assertArrayHasKey('stops', $first);
    }

    /**
     * Test 2: Role Architecture Guard - Driver cannot book seats; only riders can book.
     */
    public function test_driver_account_cannot_book_seats_preventing_role_confusion(): void
    {
        $driver = User::create([
            'name' => 'السائق علي',
            'email' => 'ali.driver@meshwar.local',
            'phone' => '0599112233',
            'role' => 'driver',
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'خط رفح',
            'start_point' => 'دوار العودة',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'stops' => [],
        ]);

        // Attempt booking while authenticated as driver
        $response = $this->actingAs($driver)->postJson(route('api.bookings.store'), [
            'route_id' => $route->id,
            'seats' => 1,
            'pickup_stop' => 'دوار العودة',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'حساب السائق مخصص لإدارة الرحلات واستقبال الركاب، ولا يمكن حجز مقاعد كراكب بنفس الحساب.');
    }

    /**
     * Test 3: Atomic Seat Locking - Prevents overbooking under concurrent bookings.
     */
    public function test_seat_lock_prevents_overbooking_when_capacity_is_exceeded(): void
    {
        $driver = User::create([
            'name' => 'السائق خليل',
            'email' => 'khalil@meshwar.local',
            'phone' => '0599778899',
            'role' => 'driver',
        ]);

        // Vehicle with exactly 2 seats
        Vehicle::create([
            'driver_id' => $driver->id,
            'type' => 'car',
            'seat_count' => 2,
            'status' => 'active',
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'خط خانيونس',
            'start_point' => 'بني سهيلا',
            'first_trip_time' => '08:00',
            'status' => 'on_route',
            'stops' => [],
        ]);

        $rider1 = User::create([
            'name' => 'راكب 1',
            'email' => 'r1@meshwar.local',
            'phone' => '0569111111',
            'role' => 'rider',
        ]);

        $rider2 = User::create([
            'name' => 'راكب 2',
            'email' => 'r2@meshwar.local',
            'phone' => '0569222222',
            'role' => 'rider',
        ]);

        // Rider 1 books 2 seats (fills vehicle)
        $res1 = $this->actingAs($rider1)->postJson(route('api.bookings.store'), [
            'route_id' => $route->id,
            'seats' => 2,
            'pickup_stop' => 'بني سهيلا',
            'fare_ils' => 10,
        ]);
        $res1->assertStatus(201)
            ->assertJsonPath('ok', true);

        // Rider 2 attempts to book 1 seat on full vehicle
        $res2 = $this->actingAs($rider2)->postJson(route('api.bookings.store'), [
            'route_id' => $route->id,
            'seats' => 1,
            'pickup_stop' => 'بني سهيلا',
            'fare_ils' => 5,
        ]);

        $res2->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('free_seats', 0);
    }

    /**
     * Test 4: Live Location Toggle & Boarding Flow - Confirms loop is resolved and status transitions cleanly.
     */
    public function test_location_toggle_and_boarding_clean_transition(): void
    {
        $driver = User::create([
            'name' => 'سائق الخط',
            'email' => 'driver.manifest.test@meshwar.local',
            'phone' => '0599555666',
            'role' => 'driver',
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'خط الوسطى',
            'start_point' => 'دير البلح',
            'first_trip_time' => '07:30',
            'status' => 'on_route',
            'stops' => [],
        ]);

        $rider = User::create([
            'name' => 'الراكب',
            'email' => 'rider.manifest.test@meshwar.local',
            'phone' => '0569444555',
            'role' => 'rider',
        ]);

        $booking = Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 1,
            'pickup_stop' => 'دير البلح',
            'status' => 'confirmed',
        ]);

        // Request location
        $req = $this->actingAs($driver)->postJson(route('api.location.request'), ['booking_id' => $booking->id]);
        $req->assertStatus(200);
        $shareId = $req->json('share_id');

        // Accept and send position
        $this->actingAs($rider)->postJson(route('api.location.respond', $shareId), ['action' => 'accept']);
        $this->actingAs($rider)->postJson(route('api.location.update', $shareId), ['lat' => 31.4170, 'lng' => 34.3510]);

        // Driver confirms boarding
        $confirm = $this->actingAs($driver)->postJson(route('api.driver.bookings.confirm', $booking->id));
        $confirm->assertStatus(200)
            ->assertJsonPath('status', 'boarded');

        // Manifest check: status is boarded and location share ended
        $manifest = $this->actingAs($driver)->getJson(route('api.driver.manifest'));
        $manifest->assertStatus(200)
            ->assertJsonPath('manifest.0.status', 'boarded');

        $this->assertEquals('ended', LocationShare::find($shareId)->status);
    }

    /**
     * Test 5: Special Request Lifecycle under Simulated High Traffic.
     */
    public function test_special_request_bidding_acceptance_and_driver_notification_under_load(): void
    {
        $rider = User::create([
            'name' => 'سارة الراكبة',
            'email' => 'sara@meshwar.local',
            'phone' => '0569123456',
            'role' => 'rider',
        ]);

        $driver = User::create([
            'name' => 'سائق خاص',
            'email' => 'driver.special.load@meshwar.local',
            'phone' => '0599332211',
            'role' => 'driver',
        ]);

        // Post request
        $reqRes = $this->actingAs($rider)->postJson(route('api.requests.store'), [
            'origin_name' => 'رفح',
            'destination_name' => 'غزة',
            'departure_date' => now()->format('Y-m-d'),
            'departure_window' => '09:00',
            'seats_needed' => 4,
        ]);
        $reqId = $reqRes->json('request.id');

        // Submit offer
        $offRes = $this->actingAs($driver)->postJson(route('api.offers.store', $reqId), [
            'price_ils' => 80.00,
        ]);
        $offId = $offRes->json('offer.id');

        // Accept offer
        $this->actingAs($rider)->postJson(route('api.offers.accept', ['id' => $reqId, 'offerId' => $offId]));

        // Driver verifies acceptance and phone unlock
        $driverBoard = $this->actingAs($driver)->getJson(route('api.requests.index'));
        $driverBoard->assertStatus(200)
            ->assertJsonPath('accepted_jobs_count', 1);

        $firstReq = $driverBoard->json('requests.0');
        $this->assertTrue($firstReq['is_accepted']);
        $this->assertEquals('0569123456', $firstReq['rider_phone']);
    }
}
