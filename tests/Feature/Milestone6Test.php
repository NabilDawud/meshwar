<?php

namespace Tests\Feature;

use App\Models\SpecialOffer;
use App\Models\SpecialRequest;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Milestone6Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Verify the Database Seeder includes one open request with two offers and a short chat.
     */
    public function test_database_seeder_includes_open_request_with_two_offers_and_chat(): void
    {
        $this->seed(DatabaseSeeder::class);

        $request = SpecialRequest::where('status', 'open')->first();
        $this->assertNotNull($request, 'Expected at least one open special request seeded');
        $this->assertEquals('دوار العودة - وسط رفح', $request->origin_name);
        $this->assertEquals('ميدان فلسطين (الساحة) - غزة', $request->destination_name);
        $this->assertEquals(3, $request->seats_needed);

        $offers = $request->offers()->with(['driver.vehicle', 'messages'])->get();
        $this->assertCount(2, $offers, 'Expected exactly two driver offers on the seeded request');

        // Check priced offers in ILS
        $prices = $offers->pluck('price_ils')->map(fn ($p) => (float) $p)->toArray();
        $this->assertContains(70.0, $prices);
        $this->assertContains(85.0, $prices);

        // Check vehicle details on offers
        $hasAjala = $offers->contains(fn ($o) => $o->driver->vehicle?->type === 'ajala');
        $hasCar = $offers->contains(fn ($o) => $o->driver->vehicle?->type === 'car');
        $this->assertTrue($hasAjala, 'One offer must have an ajala vehicle');
        $this->assertTrue($hasCar, 'One offer must have a private car vehicle');

        // Check short chat messages on offer 1
        $offerWithChat = $offers->first(fn ($o) => $o->messages->count() > 0);
        $this->assertNotNull($offerWithChat, 'Expected short chat thread on one offer');
        $this->assertGreaterThanOrEqual(3, $offerWithChat->messages->count());
    }

    /**
     * Test 2: Rider posts a private trip request with offline idempotency.
     */
    public function test_rider_can_post_special_request_with_offline_mutation_id(): void
    {
        $rider = User::create([
            'name' => 'سامي الراكب',
            'email' => 'sami@meshwar.local',
            'phone' => '0599000111',
            'role' => 'rider',
        ]);

        $payload = [
            'origin_name' => 'مستشفى شهداء الأقصى - دير البلح',
            'destination_name' => 'مستشفى الشفاء - غزة',
            'departure_date' => now()->addDay()->format('Y-m-d'),
            'departure_window' => '09:00 صباحاً',
            'seats_needed' => 2,
            'notes' => 'مشوار طبي عاجل، نرجو الالتزام بالموعد',
            'client_mutation_id' => 'client-req-uuid-12345',
        ];

        // First attempt (creates new)
        $response = $this->actingAs($rider)->postJson(route('api.requests.store'), $payload);
        $response->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('request.origin_name', 'مستشفى شهداء الأقصى - دير البلح')
            ->assertJsonPath('request.status', 'open');

        $this->assertDatabaseHas('special_requests', [
            'rider_id' => $rider->id,
            'client_mutation_id' => 'client-req-uuid-12345',
            'status' => 'open',
        ]);

        // Second attempt with same client_mutation_id (offline replay / duplicate avoidance)
        $replayResponse = $this->actingAs($rider)->postJson(route('api.requests.store'), $payload);
        $replayResponse->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('already_synced', true);

        $this->assertEquals(1, SpecialRequest::where('client_mutation_id', 'client-req-uuid-12345')->count());
    }

    /**
     * Test 3: Drivers see open requests and submit priced offers in ILS.
     */
    public function test_driver_sees_open_requests_and_submits_priced_offer(): void
    {
        $rider = User::create([
            'name' => 'محمود',
            'email' => 'rider@meshwar.local',
            'phone' => '0569876543',
            'role' => 'rider',
        ]);

        $driver = User::create([
            'name' => 'خالد السائق',
            'email' => 'driver-khaled@meshwar.local',
            'phone' => '0599654321',
            'role' => 'driver',
        ]);

        Vehicle::create([
            'driver_id' => $driver->id,
            'type' => 'car',
            'seat_count' => 4,
            'license_plate' => 'غزة - 4567',
            'status' => 'active',
            'photos' => ['/images/sample-car.svg'],
        ]);

        $specialRequest = SpecialRequest::create([
            'rider_id' => $rider->id,
            'origin_name' => 'خان يونس',
            'destination_name' => 'غزة',
            'departure_date' => now()->format('Y-m-d'),
            'departure_window' => '07:30 صباحاً',
            'seats_needed' => 3,
            'status' => 'open',
        ]);

        // Driver views opportunities
        $indexResponse = $this->actingAs($driver)->getJson(route('api.requests.index'));
        $indexResponse->assertStatus(200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('requests.0.id', $specialRequest->id)
            ->assertJsonPath('requests.0.my_offer', null);

        // Driver submits a priced offer of 75 ILS
        $offerPayload = [
            'price_ils' => 75.00,
            'notes' => 'سيارة خاصة مكيفة وجاهز في الوقت المحدد',
            'client_mutation_id' => 'client-offer-uuid-98765',
        ];

        $offerResponse = $this->actingAs($driver)->postJson(route('api.offers.store', $specialRequest->id), $offerPayload);
        $offerResponse->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('offer.price_ils', 75);

        $this->assertDatabaseHas('special_offers', [
            'request_id' => $specialRequest->id,
            'driver_id' => $driver->id,
            'price_ils' => 75.00,
            'status' => 'pending',
            'client_mutation_id' => 'client-offer-uuid-98765',
        ]);

        // Offline duplicate replay
        $replayOffer = $this->actingAs($driver)->postJson(route('api.offers.store', $specialRequest->id), $offerPayload);
        $replayOffer->assertStatus(200)
            ->assertJsonPath('already_synced', true);
    }

    /**
     * Test 4: Rider compares offers side by side with price, driver phone with tap-to-call, rating, and vehicle photo.
     */
    public function test_rider_compares_offers_side_by_side_with_driver_details(): void
    {
        $rider = User::create([
            'name' => 'محمود الراكب',
            'email' => 'rider-compare@meshwar.local',
            'phone' => '0569876543',
            'role' => 'rider',
        ]);

        $driver1 = User::create([
            'name' => 'أبو أحمد السائق',
            'email' => 'driver1@meshwar.local',
            'phone' => '0599123456',
            'role' => 'driver',
        ]);

        Vehicle::create([
            'driver_id' => $driver1->id,
            'type' => 'ajala',
            'seat_count' => 10,
            'license_plate' => 'غزة - 7890',
            'photos' => ['/images/sample-ajala.svg'],
        ]);

        $driver2 = User::create([
            'name' => 'خالد السائق',
            'email' => 'driver2@meshwar.local',
            'phone' => '0599654321',
            'role' => 'driver',
        ]);

        Vehicle::create([
            'driver_id' => $driver2->id,
            'type' => 'car',
            'seat_count' => 4,
            'license_plate' => 'غزة - 4567',
            'photos' => ['/images/sample-car.svg'],
        ]);

        $specialRequest = SpecialRequest::create([
            'rider_id' => $rider->id,
            'origin_name' => 'رفح',
            'destination_name' => 'غزة',
            'departure_date' => now()->format('Y-m-d'),
            'departure_window' => '08:00 صباحاً',
            'seats_needed' => 4,
            'status' => 'open',
        ]);

        SpecialOffer::create([
            'request_id' => $specialRequest->id,
            'driver_id' => $driver1->id,
            'price_ils' => 70.00,
            'notes' => 'سيارة واسعة للأمتعة',
            'status' => 'pending',
        ]);

        SpecialOffer::create([
            'request_id' => $specialRequest->id,
            'driver_id' => $driver2->id,
            'price_ils' => 85.00,
            'notes' => 'سيارة خاصة مكيفة',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($rider)->getJson(route('api.requests.show', $specialRequest->id));
        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('request.offers_count', 2);

        $offers = $response->json('request.offers');
        $this->assertCount(2, $offers);

        // Verify Driver 1 Offer
        $offer1 = collect($offers)->firstWhere('driver.name', 'أبو أحمد السائق');
        $this->assertEquals(70.0, $offer1['price_ils']);
        $this->assertEquals('0599123456', $offer1['driver']['phone']);
        $this->assertTrue($offer1['driver']['can_call']);
        $this->assertEquals('عجلة', $offer1['vehicle']['type_arabic']);
        $this->assertEquals('/images/sample-ajala.svg', $offer1['vehicle']['photo']);

        // Verify Driver 2 Offer
        $offer2 = collect($offers)->firstWhere('driver.name', 'خالد السائق');
        $this->assertEquals(85.0, $offer2['price_ils']);
        $this->assertEquals('0599654321', $offer2['driver']['phone']);
        $this->assertTrue($offer2['driver']['can_call']);
        $this->assertEquals('سيارة', $offer2['vehicle']['type_arabic']);
        $this->assertEquals('/images/sample-car.svg', $offer2['vehicle']['photo']);
    }

    /**
     * Test 5: Rider chats with bidding driver in a simple thread with offline idempotency.
     */
    public function test_direct_chat_thread_between_rider_and_driver(): void
    {
        $rider = User::create([
            'name' => 'محمود الراكب',
            'email' => 'rider-chat@meshwar.local',
            'phone' => '0569876543',
            'role' => 'rider',
        ]);

        $driver = User::create([
            'name' => 'أبو أحمد السائق',
            'email' => 'driver-chat@meshwar.local',
            'phone' => '0599123456',
            'role' => 'driver',
        ]);

        $specialRequest = SpecialRequest::create([
            'rider_id' => $rider->id,
            'origin_name' => 'رفح',
            'destination_name' => 'غزة',
            'departure_date' => now()->format('Y-m-d'),
            'departure_window' => '08:00 صباحاً',
            'seats_needed' => 2,
            'status' => 'open',
        ]);

        $offer = SpecialOffer::create([
            'request_id' => $specialRequest->id,
            'driver_id' => $driver->id,
            'price_ils' => 70.00,
            'status' => 'pending',
        ]);

        // Rider sends a message
        $sendResponse = $this->actingAs($rider)->postJson(route('api.chat.send', $offer->id), [
            'message' => 'هل يمكن الانطلاق مبكراً نصف ساعة؟',
            'client_mutation_id' => 'chat-mut-msg-1',
        ]);

        $sendResponse->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message.message', 'هل يمكن الانطلاق مبكراً نصف ساعة؟')
            ->assertJsonPath('message.is_me', true);

        // Driver replies
        $driverReply = $this->actingAs($driver)->postJson(route('api.chat.send', $offer->id), [
            'message' => 'نعم بالتأكيد، جاهز في أي وقت تحب.',
            'client_mutation_id' => 'chat-mut-msg-2',
        ]);

        $driverReply->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message.message', 'نعم بالتأكيد، جاهز في أي وقت تحب.');

        // Get messages list
        $listResponse = $this->actingAs($rider)->getJson(route('api.chat.messages', $offer->id));
        $listResponse->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonCount(2, 'messages');

        // Third party user cannot snoop on chat
        $intruder = User::create([
            'name' => 'متطفل',
            'email' => 'intruder@meshwar.local',
            'phone' => '0599999999',
            'role' => 'rider',
        ]);
        $forbiddenResponse = $this->actingAs($intruder)->getJson(route('api.chat.messages', $offer->id));
        $forbiddenResponse->assertStatus(403);
    }

    /**
     * Test 6: Accepting an offer books the trip, closes the request, rejects other offers,
     * and strictly enforces the Privacy Rule: only then shows rider phone to chosen driver.
     */
    public function test_accepting_offer_closes_request_and_enforces_privacy_rule(): void
    {
        $rider = User::create([
            'name' => 'محمود الراكب',
            'email' => 'rider-accept@meshwar.local',
            'phone' => '0569876543',
            'role' => 'rider',
        ]);

        $driver1 = User::create([
            'name' => 'أبو أحمد السائق',
            'email' => 'driver-chosen@meshwar.local',
            'phone' => '0599123456',
            'role' => 'driver',
        ]);

        $driver2 = User::create([
            'name' => 'خالد السائق',
            'email' => 'driver-other@meshwar.local',
            'phone' => '0599654321',
            'role' => 'driver',
        ]);

        $specialRequest = SpecialRequest::create([
            'rider_id' => $rider->id,
            'origin_name' => 'رفح',
            'destination_name' => 'غزة',
            'departure_date' => now()->format('Y-m-d'),
            'departure_window' => '08:00 صباحاً',
            'seats_needed' => 2,
            'status' => 'open',
        ]);

        $offer1 = SpecialOffer::create([
            'request_id' => $specialRequest->id,
            'driver_id' => $driver1->id,
            'price_ils' => 70.00,
            'status' => 'pending',
        ]);

        $offer2 = SpecialOffer::create([
            'request_id' => $specialRequest->id,
            'driver_id' => $driver2->id,
            'price_ils' => 85.00,
            'status' => 'pending',
        ]);

        // PRIVACY RULE CHECK 1: Before acceptance, driver1 cannot see rider phone
        $driver1PreCheck = $this->actingAs($driver1)->getJson(route('api.requests.show', $specialRequest->id));
        $driver1PreCheck->assertStatus(200)
            ->assertJsonPath('request.rider_phone', null)
            ->assertJsonPath('request.can_call_rider', false);

        // PRIVACY RULE CHECK 2: In Chat before acceptance, driver1 cannot call rider
        $chatPreCheck = $this->actingAs($driver1)->getJson(route('api.chat.messages', $offer1->id));
        $chatPreCheck->assertStatus(200)
            ->assertJsonPath('other_party.phone', null)
            ->assertJsonPath('other_party.can_call', false);

        // Rider accepts offer 1
        $acceptResponse = $this->actingAs($rider)->postJson(route('api.offers.accept', [
            'id' => $specialRequest->id,
            'offerId' => $offer1->id,
        ]));

        $acceptResponse->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('request.status', 'accepted')
            ->assertJsonPath('request.accepted_offer_id', $offer1->id);

        // Verify database statuses
        $this->assertEquals('accepted', $specialRequest->fresh()->status);
        $this->assertEquals($offer1->id, $specialRequest->fresh()->accepted_offer_id);
        $this->assertEquals('accepted', $offer1->fresh()->status);
        $this->assertEquals('rejected', $offer2->fresh()->status); // other drivers told/marked rejected

        // PRIVACY RULE CHECK 3: After acceptance, driver1's view unlocks rider's phone
        $driver1PostCheck = $this->actingAs($driver1)->getJson(route('api.requests.show', $specialRequest->id));
        $driver1PostCheck->assertStatus(200)
            ->assertJsonPath('request.rider_phone', '0569876543')
            ->assertJsonPath('request.can_call_rider', true);

        // In Chat after acceptance, driver1 can now call rider
        $chatPostCheck = $this->actingAs($driver1)->getJson(route('api.chat.messages', $offer1->id));
        $chatPostCheck->assertStatus(200)
            ->assertJsonPath('other_party.phone', '0569876543')
            ->assertJsonPath('other_party.can_call', true);

        // PRIVACY RULE CHECK 4: Competitor driver2 whose offer was rejected STILL cannot see rider phone!
        $this->assertNull($offer2->fresh()->rider_phone_for_driver);
    }
}
