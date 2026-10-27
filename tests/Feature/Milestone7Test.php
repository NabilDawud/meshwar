<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\DriverRoute;
use App\Models\SpecialRequest;
use App\Models\TripRating;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Milestone7Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Verify the Database Seeder includes a completed trip with 1-5 star ratings.
     */
    public function test_database_seeder_includes_completed_trip_with_rating(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Check completed transit booking with rating
        $completedBooking = Booking::where('status', 'completed')
            ->whereHas('rating')
            ->with(['rating', 'route.driver'])
            ->first();

        $this->assertNotNull($completedBooking, 'Expected at least one completed booking with a rating seeded');
        $this->assertEquals(5, $completedBooking->rating->rating);
        $this->assertNotEmpty($completedBooking->rating->comment);
        $this->assertEquals($completedBooking->rider_id, $completedBooking->rating->rider_id);

        // Check completed special request with rating
        $completedSpecial = SpecialRequest::where('status', 'completed')
            ->whereHas('rating')
            ->with('rating')
            ->first();

        $this->assertNotNull($completedSpecial, 'Expected at least one completed special request with a rating seeded');
        $this->assertEquals(4, $completedSpecial->rating->rating);

        // Check driver's calculated average rating
        $driver1 = User::where('email', 'driver@meshwar.local')->first();
        $this->assertNotNull($driver1);
        $this->assertGreaterThanOrEqual(4.0, $driver1->average_rating);
        $this->assertGreaterThanOrEqual(1, $driver1->ratings_count);
    }

    /**
     * Test 2: Rider views completed trip history with line bookings, special requests, and ratings.
     */
    public function test_rider_can_view_completed_trip_history(): void
    {
        $rider = User::create([
            'name' => 'سامي الراكب',
            'email' => 'sami-history@meshwar.local',
            'phone' => '0599111222',
            'role' => 'rider',
        ]);

        $driver = User::create([
            'name' => 'أبو سليم',
            'email' => 'driver-salim@meshwar.local',
            'phone' => '0599333444',
            'role' => 'driver',
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'خط رفح - خان يونس',
            'start_point' => 'رفح',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'stops' => [],
        ]);

        // Completed regular booking
        $booking = Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 2,
            'pickup_stop' => 'دوار العودة',
            'dropoff_stop' => 'دوار بني سهيلا',
            'fare_ils' => 10.00,
            'status' => 'completed',
        ]);

        // Submit 5-star rating for this booking
        TripRating::create([
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'booking_id' => $booking->id,
            'rating' => 5,
            'comment' => 'رحلة مريحة وسريعة',
        ]);

        // Another completed booking that is NOT yet rated
        $unratedBooking = Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 1,
            'pickup_stop' => 'دوار البركة',
            'dropoff_stop' => 'الساحة',
            'fare_ils' => 15.00,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($rider)->getJson(route('api.rider.history'));

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('total_trips', 2)
            ->assertJsonPath('total_spent_ils', 25);

        $trips = $response->json('trips');
        $this->assertCount(2, $trips);

        // Check rated trip
        $ratedItem = collect($trips)->firstWhere('id', $booking->id);
        $this->assertEquals(5, $ratedItem['rating']['rating']);
        $this->assertEquals('رحلة مريحة وسريعة', $ratedItem['rating']['comment']);
        $this->assertFalse($ratedItem['can_rate']);

        // Check unrated trip
        $unratedItem = collect($trips)->firstWhere('id', $unratedBooking->id);
        $this->assertNull($unratedItem['rating']);
        $this->assertTrue($unratedItem['can_rate']);
    }

    /**
     * Test 3: Driver views completed trips history, total earnings, and average rating stats.
     */
    public function test_driver_can_view_completed_trips_history_and_stats(): void
    {
        $rider = User::create([
            'name' => 'محمود',
            'email' => 'rider-stats@meshwar.local',
            'phone' => '0569876543',
            'role' => 'rider',
        ]);

        $driver = User::create([
            'name' => 'أبو أحمد السائق',
            'email' => 'driver-stats@meshwar.local',
            'phone' => '0599123456',
            'role' => 'driver',
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'خط صلاح الدين',
            'start_point' => 'رفح',
            'first_trip_time' => '06:00',
            'status' => 'on_route',
            'stops' => [],
        ]);

        $booking = Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 3,
            'pickup_stop' => 'رفح',
            'dropoff_stop' => 'غزة',
            'fare_ils' => 30.00,
            'status' => 'completed',
        ]);

        TripRating::create([
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'booking_id' => $booking->id,
            'rating' => 5,
            'comment' => 'سائق أمين وملتزم',
        ]);

        $response = $this->actingAs($driver)->getJson(route('api.driver.history'));

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('stats.total_completed_trips', 1)
            ->assertJsonPath('stats.total_earned_ils', 30)
            ->assertJsonPath('stats.average_rating', 5)
            ->assertJsonPath('stats.ratings_count', 1);

        $trip = $response->json('trips.0');
        $this->assertEquals('محمود', $trip['rider_name']);
        $this->assertEquals(5, $trip['rating']['rating']);
        $this->assertEquals('سائق أمين وملتزم', $trip['rating']['comment']);
    }

    /**
     * Test 4: Marking a booking as completed after arrival.
     */
    public function test_driver_can_mark_booking_as_completed(): void
    {
        $rider = User::create([
            'name' => 'محمود',
            'email' => 'rider-complete@meshwar.local',
            'phone' => '0569876543',
            'role' => 'rider',
        ]);

        $driver = User::create([
            'name' => 'أبو أحمد',
            'email' => 'driver-complete@meshwar.local',
            'phone' => '0599123456',
            'role' => 'driver',
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'خط رفح غزة',
            'start_point' => 'رفح',
            'first_trip_time' => '06:00',
            'status' => 'on_route',
            'stops' => [],
        ]);

        $booking = Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 1,
            'pickup_stop' => 'رفح',
            'dropoff_stop' => 'غزة',
            'fare_ils' => 20.00,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($driver)->postJson(route('api.bookings.complete', $booking->id));

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'completed');

        $this->assertEquals('completed', $booking->fresh()->status);
    }

    /**
     * Test 5: Rider submits 1-5 star rating with feedback and offline idempotency.
     */
    public function test_rider_submits_trip_rating_with_offline_idempotency(): void
    {
        $rider = User::create([
            'name' => 'محمود الراكب',
            'email' => 'rider-rate@meshwar.local',
            'phone' => '0569876543',
            'role' => 'rider',
        ]);

        $driver = User::create([
            'name' => 'خالد السائق',
            'email' => 'driver-khaled-rate@meshwar.local',
            'phone' => '0599654321',
            'role' => 'driver',
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'خط خانيونس',
            'start_point' => 'خانيونس',
            'first_trip_time' => '08:00',
            'status' => 'on_route',
            'stops' => [],
        ]);

        $booking = Booking::create([
            'rider_id' => $rider->id,
            'route_id' => $route->id,
            'seats' => 2,
            'pickup_stop' => 'دوار بني سهيلا',
            'dropoff_stop' => 'ميدان فلسطين',
            'fare_ils' => 15.00,
            'status' => 'completed',
        ]);

        $payload = [
            'trip_type' => 'line',
            'trip_id' => $booking->id,
            'rating' => 5,
            'comment' => 'تجربة ممتازة وسائق محترم جداً',
            'client_mutation_id' => 'client-rate-uuid-55555',
        ];

        // 1. Submit rating
        $response = $this->actingAs($rider)->postJson(route('api.trips.rate'), $payload);

        $response->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('rating.rating', 5)
            ->assertJsonPath('rating.comment', 'تجربة ممتازة وسائق محترم جداً');

        $this->assertDatabaseHas('trip_ratings', [
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'booking_id' => $booking->id,
            'rating' => 5,
            'client_mutation_id' => 'client-rate-uuid-55555',
        ]);

        // 2. Offline duplicate replay
        $replay = $this->actingAs($rider)->postJson(route('api.trips.rate'), $payload);
        $replay->assertStatus(200)
            ->assertJsonPath('already_synced', true);

        // 3. Driver's average rating reflects this review
        $this->assertEquals(5.0, $driver->fresh()->average_rating);
    }

    /**
     * Test 6: Security and validation rules for rating submission.
     */
    public function test_rating_validation_and_security_guards(): void
    {
        $rider1 = User::create([
            'name' => 'محمود',
            'email' => 'rider1-sec@meshwar.local',
            'phone' => '0569876543',
            'role' => 'rider',
        ]);

        $rider2 = User::create([
            'name' => 'طارق',
            'email' => 'rider2-sec@meshwar.local',
            'phone' => '0569111222',
            'role' => 'rider',
        ]);

        $driver = User::create([
            'name' => 'السائق',
            'email' => 'driver-sec@meshwar.local',
            'phone' => '0599000111',
            'role' => 'driver',
        ]);

        $route = DriverRoute::create([
            'driver_id' => $driver->id,
            'name' => 'خط صلاح الدين',
            'start_point' => 'رفح',
            'first_trip_time' => '07:00',
            'status' => 'on_route',
            'stops' => [],
        ]);

        // Trip is confirmed but NOT YET completed
        $activeBooking = Booking::create([
            'rider_id' => $rider1->id,
            'route_id' => $route->id,
            'seats' => 1,
            'pickup_stop' => 'رفح',
            'dropoff_stop' => 'غزة',
            'fare_ils' => 20.00,
            'status' => 'confirmed',
        ]);

        // Guard 1: Cannot rate an active/incomplete trip
        $prematureResponse = $this->actingAs($rider1)->postJson(route('api.trips.rate'), [
            'trip_type' => 'line',
            'trip_id' => $activeBooking->id,
            'rating' => 5,
        ]);
        $prematureResponse->assertStatus(422)
            ->assertJsonPath('error', 'لا يمكن تقييم رحلة غير مكتملة');

        // Mark completed
        $activeBooking->update(['status' => 'completed']);

        // Guard 2: Other rider cannot rate rider1's trip
        $intruderResponse = $this->actingAs($rider2)->postJson(route('api.trips.rate'), [
            'trip_type' => 'line',
            'trip_id' => $activeBooking->id,
            'rating' => 5,
        ]);
        $intruderResponse->assertStatus(403);

        // Guard 3: Rating must be 1 to 5
        $invalidRating = $this->actingAs($rider1)->postJson(route('api.trips.rate'), [
            'trip_type' => 'line',
            'trip_id' => $activeBooking->id,
            'rating' => 6,
        ]);
        $invalidRating->assertStatus(422);
    }
}
