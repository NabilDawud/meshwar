<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\DriverRoute;
use App\Models\LocationShare;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\RouteDriftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Milestone5Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to set up a clean driver, 10-seat vehicle, and route.
     */
    protected function setupDriverAndRoute(): array
    {
        $driver = User::create([
            'name' => 'سائق الخط التجريبي',
            'email' => 'driver-m5@meshwar.local',
            'phone' => '0599111222',
            'role' => 'driver',
        ]);

        $vehicle = Vehicle::create([
            'driver_id' => $driver->id,
            'type' => 'ajala',
            'seat_count' => 10,
            'license_plate' => 'غزة - 1234',
            'status' => 'active',
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'خط صلاح الدين الرئيسي',
            'start_point' => 'دوار العودة - رفح',
            'first_trip_time' => '06:30',
            'status' => 'on_route',
            'is_trip_active' => true,
            'last_latitude' => 31.2828,
            'last_longitude' => 34.2536,
            'last_landmark' => 'دوار العودة',
            'last_position_at' => now(),
            'stops' => [
                ['order' => 1, 'name' => 'دوار العودة - رفح', 'lat' => 31.2828, 'lng' => 34.2536, 'fare_ils' => 0],
                ['order' => 2, 'name' => 'دوار بني سهيلا', 'lat' => 31.3456, 'lng' => 34.3182, 'fare_ils' => 5],
                ['order' => 3, 'name' => 'ميدان فلسطين', 'lat' => 31.5080, 'lng' => 34.4630, 'fare_ils' => 20],
            ],
        ]);

        $rider = User::create([
            'name' => 'راكب التجربة',
            'email' => 'rider-m5@meshwar.local',
            'phone' => '0569333444',
            'role' => 'rider',
        ]);

        return [$driver, $vehicle, $route, $rider];
    }

    /**
     * Test 1: Authenticated rider books seats atomically.
     */
    public function test_authenticated_rider_can_book_seats_atomically(): void
    {
        [$driver, $vehicle, $route, $rider] = $this->setupDriverAndRoute();

        $response = $this->actingAs($rider)->postJson('/api/bookings', [
            'route_id' => $route->id,
            'seats' => 2,
            'pickup_stop' => 'دوار العودة - رفح',
            'dropoff_stop' => 'دوار بني سهيلا',
            'client_mutation_id' => 'mut-offline-001',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'ok' => true,
                'message' => 'تم تأكيد حجزك بنجاح',
                'booking' => [
                    'seats' => 2,
                    'status' => 'confirmed',
                    'pickup_stop' => 'دوار العودة - رفح',
                ],
            ]);

        $this->assertDatabaseHas('bookings', [
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 2,
            'status' => 'confirmed',
            'client_mutation_id' => 'mut-offline-001',
        ]);

        // Route booked seats count is now 2; free seats is 8
        $this->assertEquals(2, $route->bookedSeatsCount());
        $this->assertEquals(8, $route->freeSeatsCount());
    }

    /**
     * Test 2: Booking strictly rejects when vehicle capacity is exceeded (no overselling).
     */
    public function test_booking_strictly_rejects_when_vehicle_capacity_is_exceeded(): void
    {
        [$driver, $vehicle, $route, $rider] = $this->setupDriverAndRoute();

        // Already 9 confirmed seats
        Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 9,
            'status' => 'confirmed',
        ]);

        // Attempting to book 2 seats when only 1 is free
        $response = $this->actingAs($rider)->postJson('/api/bookings', [
            'route_id' => $route->id,
            'seats' => 2,
            'pickup_stop' => 'دوار العودة - رفح',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'ok' => false,
                'free_seats' => 1,
            ]);

        $this->assertStringContainsString('لم تتبقَ مقاعد كافية', $response->json('error'));
    }

    /**
     * Test 3: Driver manifest returns booked riders with tap-to-call phone number.
     */
    public function test_driver_manifest_returns_booked_riders_with_tap_to_call(): void
    {
        [$driver, $vehicle, $route, $rider] = $this->setupDriverAndRoute();

        Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 1,
            'pickup_stop' => 'دوار بني سهيلا',
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($driver)->getJson('/api/driver/manifest');

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'booked_seats' => 1,
                'free_seats' => 9,
            ]);

        $manifest = $response->json('manifest');
        $this->assertCount(1, $manifest);
        $this->assertEquals('راكب التجربة', $manifest[0]['rider_name']);
        $this->assertEquals('0569333444', $manifest[0]['rider_phone']);
        $this->assertTrue($manifest[0]['can_call']);
    }

    /**
     * Test 4: Privacy Rule: Rider phone number is revealed to driver ONLY on confirmed booking.
     */
    public function test_privacy_rule_rider_phone_number_only_revealed_on_confirmed_booking(): void
    {
        [$driver, $vehicle, $route, $rider] = $this->setupDriverAndRoute();

        // Cancelled booking
        Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 1,
            'status' => 'cancelled_by_driver',
            'cancellation_reason' => 'غير جاهز',
        ]);

        $response = $this->actingAs($driver)->getJson('/api/driver/manifest');

        $manifest = $response->json('manifest');
        $this->assertNull($manifest[0]['rider_phone']);
        $this->assertFalse($manifest[0]['can_call']);
    }

    /**
     * Test 5: Privacy Rule: Driver phone number is revealed to rider ONLY on confirmed booking.
     */
    public function test_privacy_rule_driver_phone_number_only_revealed_to_rider_on_confirmed_booking(): void
    {
        [$driver, $vehicle, $route, $rider] = $this->setupDriverAndRoute();

        $booking = Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 1,
            'status' => 'confirmed',
        ]);

        // When confirmed: driver phone is visible with tap-to-call
        $response = $this->actingAs($rider)->getJson('/api/rider/bookings');
        $bookings = $response->json('bookings');
        $this->assertEquals('0599111222', $bookings[0]['driver']['phone']);
        $this->assertTrue($bookings[0]['driver']['can_call']);

        // When cancelled: driver phone is masked
        $booking->update(['status' => 'cancelled_by_driver']);
        $response = $this->actingAs($rider)->getJson('/api/rider/bookings');
        $bookings = $response->json('bookings');
        $this->assertNull($bookings[0]['driver']['phone']);
        $this->assertFalse($bookings[0]['driver']['can_call']);
    }

    /**
     * Test 6: Driver can cancel unready rider before trip start with specific in-app notice.
     */
    public function test_driver_can_cancel_unready_rider_with_cancellation_reason(): void
    {
        [$driver, $vehicle, $route, $rider] = $this->setupDriverAndRoute();

        $booking = Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 2,
            'status' => 'confirmed',
        ]);

        $this->assertEquals(2, $route->bookedSeatsCount());

        $response = $this->actingAs($driver)->postJson("/api/driver/bookings/{$booking->id}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'status' => 'cancelled_by_driver',
            ]);

        $booking->refresh();
        $this->assertEquals('cancelled_by_driver', $booking->status);
        $this->assertStringContainsString('تم إلغاء حجزك من قِبل السائق لعدم الجاهزية عند بدء الرحلة', $booking->cancellation_reason);

        // Cancelled booking frees up the seats immediately
        $this->assertEquals(0, $route->bookedSeatsCount());
        $this->assertEquals(10, $route->freeSeatsCount());
    }

    /**
     * Test 7: Route drift alert triggers when vehicle strays beyond corridor stop buffer.
     */
    public function test_route_drift_detection_flags_vehicle_when_drifting_off_corridor(): void
    {
        [$driver, $vehicle, $route, $rider] = $this->setupDriverAndRoute();

        Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 1,
            'status' => 'confirmed',
        ]);

        $driftService = new RouteDriftService;

        // Vehicle near corridor stop (Rafah: 31.2828, 34.2536) -> Not drifting
        $route->update(['last_latitude' => 31.2830, 'last_longitude' => 34.2540, 'is_trip_active' => true]);
        $check1 = $driftService->checkDrift($route);
        $this->assertFalse($check1['drifting']);

        // Vehicle drifted far off-corridor into dunes/detour (31.3500, 34.2000 > 5km away) -> Drifting!
        $route->update(['last_latitude' => 31.3500, 'last_longitude' => 34.2000, 'is_trip_active' => true]);
        $check2 = $driftService->checkDrift($route);
        $this->assertTrue($check2['drifting']);
        $this->assertStringContainsString('المركبة غيرت مسارها حالياً', $check2['alert']);

        // Rider bookings API receives the drift alert banner
        $response = $this->actingAs($rider)->getJson('/api/rider/bookings');
        $bookings = $response->json('bookings');
        $this->assertTrue($bookings[0]['route']['drifting']);
        $this->assertStringContainsString('المركبة غيرت مسارها حالياً', $bookings[0]['route']['drift_alert']);
    }

    /**
     * Test 8: Driver requests live location with 15-minute expiration window.
     */
    public function test_driver_can_request_rider_live_location_with_15_minute_expiration(): void
    {
        [$driver, $vehicle, $route, $rider] = $this->setupDriverAndRoute();

        $booking = Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 1,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($driver)->postJson('/api/location-shares/request', [
            'booking_id' => $booking->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'status' => 'requested',
            ]);

        $this->assertDatabaseHas('location_shares', [
            'booking_id' => $booking->id,
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'status' => 'requested',
        ]);

        $share = LocationShare::where('booking_id', $booking->id)->first();
        $this->assertTrue($share->expires_at->isFuture());
        $this->assertLessThanOrEqual(15, now()->diffInMinutes($share->expires_at));
    }

    /**
     * Test 9: Rider explicit consent (Yes/No) for live location sharing.
     */
    public function test_rider_explicit_consent_yes_or_no_for_location_share(): void
    {
        [$driver, $vehicle, $route, $rider] = $this->setupDriverAndRoute();

        $booking = Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 1,
            'status' => 'confirmed',
        ]);

        $share = LocationShare::create([
            'booking_id' => $booking->id,
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'status' => 'requested',
            'expires_at' => now()->addMinutes(15),
        ]);

        // Case A: Rider accepts (Yes)
        $response = $this->actingAs($rider)->postJson("/api/location-shares/{$share->id}/respond", [
            'action' => 'accept',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'status' => 'active',
                'message' => 'أنت تشارك موقعك المباشر الآن مع السائق',
            ]);

        $share->refresh();
        $this->assertEquals('active', $share->status);
        $this->assertTrue($share->isActive());

        // Case B: Rider rejects (No)
        $responseReject = $this->actingAs($rider)->postJson("/api/location-shares/{$share->id}/respond", [
            'action' => 'reject',
        ]);

        $responseReject->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'status' => 'rejected',
            ]);

        $share->refresh();
        $this->assertEquals('rejected', $share->status);
        $this->assertFalse($share->isActive());
    }

    /**
     * Test 10: Privacy Rule: Ending location sharing purges coordinates from database.
     */
    public function test_privacy_rule_ending_location_share_purges_coordinates(): void
    {
        [$driver, $vehicle, $route, $rider] = $this->setupDriverAndRoute();

        $booking = Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 1,
            'status' => 'confirmed',
        ]);

        $share = LocationShare::create([
            'booking_id' => $booking->id,
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'status' => 'active',
            'last_latitude' => 31.2828,
            'last_longitude' => 34.2536,
            'last_shared_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);

        // Rider or driver ends location sharing
        $response = $this->actingAs($rider)->postJson("/api/location-shares/{$share->id}/end");

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'status' => 'ended',
            ]);

        $share->refresh();
        $this->assertEquals('ended', $share->status);
        $this->assertNull($share->last_latitude, 'Privacy standard: coordinates must be purged on termination');
        $this->assertNull($share->last_longitude, 'Privacy standard: coordinates must be purged on termination');

        // Subsequent update attempt must be rejected
        $updateResp = $this->actingAs($rider)->postJson("/api/location-shares/{$share->id}/update", [
            'lat' => 31.2828,
            'lng' => 34.2536,
        ]);
        $updateResp->assertStatus(403);
    }

    /**
     * Test 11: Offline outbox idempotency with client_mutation_id.
     */
    public function test_offline_outbox_mutation_syncs_idempotently(): void
    {
        [$driver, $vehicle, $route, $rider] = $this->setupDriverAndRoute();

        $payload = [
            'route_id' => $route->id,
            'seats' => 1,
            'pickup_stop' => 'دوار العودة',
            'client_mutation_id' => 'offline-token-xyz-999',
        ];

        // First sync
        $res1 = $this->actingAs($rider)->postJson('/api/bookings', $payload);
        $res1->assertStatus(201)->assertJson(['ok' => true]);

        // Duplicate sync (e.g., reconnect retry)
        $res2 = $this->actingAs($rider)->postJson('/api/bookings', $payload);
        $res2->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'already_synced' => true,
            ]);

        // Total bookings in database must still be 1 (no duplicate seat allocation)
        $this->assertEquals(1, Booking::where('client_mutation_id', 'offline-token-xyz-999')->count());
        $this->assertEquals(1, $route->bookedSeatsCount());
    }
}
