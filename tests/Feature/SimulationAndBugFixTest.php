<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\DriverRoute;
use App\Models\LocationShare;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationAndBugFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SIMULATION 1:
     * - Rider posts special request
     * - Driver submits priced offer
     * - Rider accepts offer
     * - BUG FIX VERIFICATION: Driver CAN see the request in /api/requests with is_accepted=true and unlocked phone!
     * - Driver can complete the trip
     */
    public function test_simulation_special_request_accepted_is_visible_and_notified_to_driver(): void
    {
        $rider = User::create([
            'name' => 'محمود الراكب',
            'email' => 'mahmoud-sim@meshwar.local',
            'phone' => '0569876543',
            'role' => 'rider',
        ]);

        $driver = User::create([
            'name' => 'أبو أحمد السائق',
            'email' => 'driver-sim@meshwar.local',
            'phone' => '0599123456',
            'role' => 'driver',
        ]);

        Vehicle::create([
            'driver_id' => $driver->id,
            'type' => 'car',
            'seat_count' => 4,
            'license_plate' => 'غزة - 7890',
            'status' => 'active',
        ]);

        // 1. Rider posts private trip request
        $postResponse = $this->actingAs($rider)->postJson(route('api.requests.store'), [
            'origin_name' => 'دوار العودة - رفح',
            'destination_name' => 'ميدان فلسطين - غزة',
            'departure_date' => now()->format('Y-m-d'),
            'departure_window' => '08:00 صباحاً',
            'seats_needed' => 3,
            'notes' => 'مشوار عائلي خاص',
        ]);
        $postResponse->assertStatus(201);
        $requestId = $postResponse->json('request.id');

        // 2. Driver sees open request
        $driverIndex1 = $this->actingAs($driver)->getJson(route('api.requests.index'));
        $driverIndex1->assertStatus(200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('requests.0.id', $requestId)
            ->assertJsonPath('requests.0.my_offer', null);

        // 3. Driver submits priced offer of 70 ILS
        $offerResponse = $this->actingAs($driver)->postJson(route('api.offers.store', $requestId), [
            'price_ils' => 70.00,
            'notes' => 'سيارة خاصة مكيفة وجاهز فوراً',
        ]);
        $offerResponse->assertStatus(201);
        $offerId = $offerResponse->json('offer.id');

        // 4. Rider views request and accepts this driver's offer
        $acceptResponse = $this->actingAs($rider)->postJson(route('api.offers.accept', [
            'id' => $requestId,
            'offerId' => $offerId,
        ]));
        $acceptResponse->assertStatus(200)
            ->assertJsonPath('request.status', 'accepted')
            ->assertJsonPath('request.accepted_offer_id', $offerId);

        // 5. CRITICAL BUG CHECK: Driver checks /api/requests AFTER acceptance
        // The request MUST NOT disappear! It must be returned with is_accepted=true and unlocked rider_phone!
        $driverIndex2 = $this->actingAs($driver)->getJson(route('api.requests.index'));
        $driverIndex2->assertStatus(200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('accepted_jobs_count', 1);

        $driverReq = $driverIndex2->json('requests.0');
        $this->assertEquals($requestId, $driverReq['id']);
        $this->assertEquals('accepted', $driverReq['status']);
        $this->assertTrue($driverReq['is_accepted']);
        $this->assertTrue($driverReq['can_call_rider']);
        $this->assertEquals('0569876543', $driverReq['rider_phone']);
        $this->assertEquals('accepted', $driverReq['my_offer']['status']);
        $this->assertEquals(70.0, $driverReq['my_offer']['price_ils']);

        // 6. Driver completes special request
        $completeResponse = $this->actingAs($driver)->postJson(route('api.requests.complete', $requestId));
        $completeResponse->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'completed');
    }

    /**
     * SIMULATION 2:
     * - Driver requests location from rider
     * - Rider accepts and transmits GPS
     * - Driver confirms rider boarded ("تأكيد الصعود" / sure)
     * - BUG FIX VERIFICATION: Booking transitions to 'boarded', location share ends, and location request button DOES NOT loop/reappear!
     * - Driver completes trip upon arrival
     * - Rider rates 5 stars
     */
    public function test_simulation_location_share_to_boarding_to_completion_flow(): void
    {
        $rider = User::create([
            'name' => 'محمود',
            'email' => 'mahmoud-loc@meshwar.local',
            'phone' => '0569876543',
            'role' => 'rider',
        ]);

        $driver = User::create([
            'name' => 'أبو أحمد',
            'email' => 'driver-loc@meshwar.local',
            'phone' => '0599123456',
            'role' => 'driver',
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'خط رفح غزة',
            'start_point' => 'رفح',
            'first_trip_time' => '06:30',
            'status' => 'on_route',
            'stops' => [],
        ]);

        $booking = Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 2,
            'pickup_stop' => 'دوار العودة',
            'dropoff_stop' => 'دوار بني سهيلا',
            'fare_ils' => 10.00,
            'status' => 'confirmed',
        ]);

        // 1. Driver requests live location
        $reqLocation = $this->actingAs($driver)->postJson(route('api.location.request'), [
            'booking_id' => $booking->id,
        ]);
        $reqLocation->assertStatus(200)
            ->assertJsonPath('status', 'requested');
        $shareId = $reqLocation->json('share_id');

        // 2. Rider accepts location sharing
        $respondResponse = $this->actingAs($rider)->postJson(route('api.location.respond', $shareId), [
            'action' => 'accept',
        ]);
        $respondResponse->assertStatus(200)
            ->assertJsonPath('status', 'active');

        // 3. Rider transmits GPS coordinates
        $updateResponse = $this->actingAs($rider)->postJson(route('api.location.update', $shareId), [
            'lat' => 31.2828,
            'lng' => 34.2536,
        ]);
        $updateResponse->assertStatus(200);

        // 4. Driver manifest shows active location
        $manifest1 = $this->actingAs($driver)->getJson(route('api.driver.manifest'));
        $manifest1->assertStatus(200)
            ->assertJsonPath('manifest.0.location_share.is_active', true)
            ->assertJsonPath('manifest.0.location_share.last_latitude', '31.2828000');

        // 5. Driver confirms passenger boarded ("تأكيد الصعود" / sure)
        $confirmResponse = $this->actingAs($driver)->postJson(route('api.driver.bookings.confirm', $booking->id));
        $confirmResponse->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'boarded');

        // 6. CRITICAL BUG CHECK: Manifest after confirmation
        // Status must be 'boarded', NOT 'confirmed', so location sharing does NOT loop back to "طلب موقع الراكب"!
        $manifest2 = $this->actingAs($driver)->getJson(route('api.driver.manifest'));
        $manifest2->assertStatus(200)
            ->assertJsonPath('manifest.0.status', 'boarded')
            ->assertJsonPath('manifest.0.can_call', true); // Phone stays accessible!

        $shareFresh = LocationShare::find($shareId);
        $this->assertEquals('ended', $shareFresh->status);
        $this->assertEquals('boarded', $booking->fresh()->status);

        // 7. Driver completes trip
        $completeResponse = $this->actingAs($driver)->postJson(route('api.bookings.complete', $booking->id));
        $completeResponse->assertStatus(200)
            ->assertJsonPath('status', 'completed');

        // 8. Rider rates trip 5 stars
        $rateResponse = $this->actingAs($rider)->postJson(route('api.trips.rate'), [
            'trip_type' => 'line',
            'trip_id' => $booking->id,
            'rating' => 5,
            'comment' => 'سائق ممتاز ورحلة رائعة',
        ]);
        $rateResponse->assertStatus(201);

        $this->assertEquals(5.0, $driver->fresh()->average_rating);
    }
}
