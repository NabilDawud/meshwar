<?php

namespace Tests\Feature;

use App\Models\DriverRoute;
use App\Models\SpecialOffer;
use App\Models\SpecialRequest;
use App\Models\TripRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverRatingAccuracyTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_driver_without_reviews_has_zero_rating_not_fake_4_9(): void
    {
        $driver = User::create([
            'name' => 'سائق جديد',
            'email' => 'new-driver@meshwar.local',
            'phone' => '0599000111',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);

        $this->assertEquals(0, $driver->ratings_count);
        $this->assertEquals(0.0, $driver->average_rating, 'Unrated driver must return 0.0, never fake 4.9');
    }

    public function test_driver_with_ratings_calculates_exact_average(): void
    {
        $driver = User::create([
            'name' => 'سائق مجرب',
            'email' => 'rated-driver@meshwar.local',
            'phone' => '0599000222',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);

        $rider1 = User::create([
            'name' => 'راكب 1',
            'email' => 'r1@meshwar.local',
            'phone' => '0599000333',
            'role' => 'rider',
            'password' => bcrypt('password'),
        ]);

        $rider2 = User::create([
            'name' => 'راكب 2',
            'email' => 'r2@meshwar.local',
            'phone' => '0599000444',
            'role' => 'rider',
            'password' => bcrypt('password'),
        ]);

        TripRating::create([
            'driver_id' => $driver->id,
            'rider_id' => $rider1->id,
            'rating' => 5,
            'comment' => 'سائق ممتاز',
        ]);

        TripRating::create([
            'driver_id' => $driver->id,
            'rider_id' => $rider2->id,
            'rating' => 3,
            'comment' => 'جيد',
        ]);

        $this->assertEquals(2, $driver->fresh()->ratings_count);
        $this->assertEquals(4.0, $driver->fresh()->average_rating);
    }

    public function test_special_request_offer_reflects_actual_driver_rating_not_hardcoded_4_9(): void
    {
        $rider = User::create([
            'name' => 'طالب الرحلة',
            'email' => 'req-rider@meshwar.local',
            'phone' => '0599000555',
            'role' => 'rider',
            'password' => bcrypt('password'),
        ]);

        $unratedDriver = User::create([
            'name' => 'سائق بدون تقييم',
            'email' => 'no-rate@meshwar.local',
            'phone' => '0599000666',
            'role' => 'driver',
            'password' => bcrypt('password'),
        ]);

        $request = SpecialRequest::create([
            'rider_id' => $rider->id,
            'origin_name' => 'غزة',
            'destination_name' => 'خانيونس',
            'departure_date' => now()->toDateString(),
            'departure_window' => '08:00 - 10:00',
            'seats_needed' => 2,
            'status' => 'open',
        ]);

        SpecialOffer::create([
            'request_id' => $request->id,
            'driver_id' => $unratedDriver->id,
            'price_ils' => 50,
            'status' => 'pending',
        ]);

        $this->actingAs($rider);

        $response = $this->getJson(route('api.requests.show', $request->id));
        $response->assertOk();

        $offers = $response->json('request.offers');
        $this->assertCount(1, $offers);
        $this->assertEquals(0.0, $offers[0]['driver']['rating'], 'Unrated driver offer must show 0.0, not hardcoded 4.9');
    }
}
