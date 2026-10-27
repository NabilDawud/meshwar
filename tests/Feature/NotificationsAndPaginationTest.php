<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\DriverRoute;
use App\Models\SpecialOffer;
use App\Models\SpecialRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\MishwarNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsAndPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected User $rider;

    protected User $driver;

    protected Vehicle $vehicle;

    protected DriverRoute $route;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rider = User::create([
            'name' => 'محمد الراكب',
            'email' => 'rider@meshwar.local',
            'phone' => '0599111222',
            'role' => 'rider',
        ]);

        $this->driver = User::create([
            'name' => 'خليل السائق',
            'email' => 'driver@meshwar.local',
            'phone' => '0599333444',
            'role' => 'driver',
        ]);

        $this->vehicle = Vehicle::create([
            'driver_id' => $this->driver->id,
            'type' => 'car',
            'seat_count' => 4,
            'license_plate' => '7-1234-99',
            'status' => 'active',
        ]);

        $this->route = DriverRoute::create([
            'driver_id' => $this->driver->id,
            'name' => 'رفح - خانيونس',
            'start_point' => 'دوار العودة',
            'first_trip_time' => '07:30',
            'status' => 'on_route',
            'is_trip_active' => true,
            'last_latitude' => 31.2825,
            'last_longitude' => 34.2541,
            'last_landmark' => 'دوار العودة',
            'last_position_at' => now(),
            'stops' => [
                ['name' => 'دوار العودة', 'lat' => 31.2825, 'lng' => 34.2541, 'order' => 1, 'fare_ils' => 0],
                ['name' => 'بني سهيلا', 'lat' => 31.3456, 'lng' => 34.3182, 'order' => 2, 'fare_ils' => 5],
            ],
        ]);
    }

    /**
     * Test notification endpoints (fetch, mark single as read, mark all as read).
     */
    public function test_notifications_api_endpoints_work_correctly(): void
    {
        // Send two notifications to rider
        $this->rider->notify(new MishwarNotification(
            title: 'إشعار تجريبي 1',
            message: 'محتوى الإشعار الأول',
            type: 'booking',
            icon: '🚐'
        ));

        $this->rider->notify(new MishwarNotification(
            title: 'إشعار تجريبي 2',
            message: 'محتوى الإشعار الثاني',
            type: 'offer',
            icon: '💼'
        ));

        $this->actingAs($this->rider);

        // 1. Fetch notifications
        $response = $this->getJson(route('api.notifications.index'));
        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('unread_count', 2);

        $notifications = $response->json('notifications');
        $this->assertCount(2, $notifications);
        $firstId = $notifications[0]['id'];

        // 2. Mark first notification as read
        $readRes = $this->postJson(route('api.notifications.read', $firstId));
        $readRes->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('unread_count', 1);

        // 3. Mark all as read
        $allRes = $this->postJson(route('api.notifications.read_all'));
        $allRes->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('unread_count', 0);

        // Verify in database
        $this->assertEquals(0, $this->rider->unreadNotifications()->count());
    }

    /**
     * Test seat booking notifies the driver.
     */
    public function test_booking_a_seat_notifies_driver(): void
    {
        $this->actingAs($this->rider);

        $response = $this->postJson(route('api.bookings.store'), [
            'route_id' => $this->route->id,
            'seats' => 2,
            'pickup_stop' => 'دوار العودة',
            'dropoff_stop' => 'بني سهيلا',
            'fare_ils' => 10,
        ]);

        $response->assertStatus(201);

        // Driver must have received a database notification
        $this->assertEquals(1, $this->driver->unreadNotifications()->count());
        $notification = $this->driver->unreadNotifications()->first();
        $this->assertStringContainsString('حجز مقعد جديد', $notification->data['title']);
        $this->assertStringContainsString('محمد الراكب', $notification->data['message']);
        $this->assertEquals('booking', $notification->data['type']);
    }

    /**
     * Test driver confirming boarding notifies the rider.
     */
    public function test_driver_confirming_boarding_notifies_rider(): void
    {
        $booking = Booking::create([
            'rider_id' => $this->rider->id,
            'route_id' => $this->route->id,
            'seats' => 1,
            'pickup_stop' => 'دوار العودة',
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->driver);

        $response = $this->postJson(route('api.driver.bookings.confirm', $booking->id));
        $response->assertStatus(200);

        // Rider must have received a boarding notification
        $this->assertEquals(1, $this->rider->unreadNotifications()->count());
        $notification = $this->rider->unreadNotifications()->first();
        $this->assertStringContainsString('تأكيد صعودك للمركبة', $notification->data['title']);
        $this->assertEquals('booking', $notification->data['type']);
    }

    /**
     * Test location sharing request and response notify rider and driver.
     */
    public function test_location_sharing_lifecycle_notifications(): void
    {
        $booking = Booking::create([
            'rider_id' => $this->rider->id,
            'route_id' => $this->route->id,
            'seats' => 1,
            'pickup_stop' => 'دوار العودة',
            'status' => 'confirmed',
        ]);

        // 1. Driver requests location -> rider notified
        $this->actingAs($this->driver);
        $reqRes = $this->postJson(route('api.location.request'), [
            'booking_id' => $booking->id,
        ]);
        $reqRes->assertStatus(200);
        $shareId = $reqRes->json('share_id');

        $this->assertEquals(1, $this->rider->unreadNotifications()->count());
        $this->assertStringContainsString('مشاركة الموقع', $this->rider->unreadNotifications()->first()->data['title']);

        // 2. Rider accepts location -> driver notified
        $this->actingAs($this->rider);
        $acceptRes = $this->postJson(route('api.location.respond', $shareId), [
            'action' => 'accept',
        ]);
        $acceptRes->assertStatus(200);

        $this->assertEquals(1, $this->driver->unreadNotifications()->count());
        $this->assertStringContainsString('تمت الموافقة على مشاركة الموقع', $this->driver->unreadNotifications()->first()->data['title']);
    }

    /**
     * Test special request offer submission and acceptance notifications.
     */
    public function test_special_offer_and_acceptance_notifications(): void
    {
        // 1. Rider creates special request
        $specialRequest = SpecialRequest::create([
            'rider_id' => $this->rider->id,
            'origin_name' => 'خانيونس - البلد',
            'destination_name' => 'غزة - الرمال',
            'departure_date' => now()->addDay()->toDateString(),
            'departure_window' => 'صباحاً (08:00 - 10:00)',
            'seats_needed' => 4,
            'status' => 'open',
        ]);

        // Second driver who will also bid and get rejected notification
        $otherDriver = User::create([
            'name' => 'ماجد السائق',
            'email' => 'majed@meshwar.local',
            'phone' => '0599555666',
            'role' => 'driver',
        ]);
        Vehicle::create([
            'driver_id' => $otherDriver->id,
            'type' => 'car',
            'seat_count' => 4,
            'license_plate' => '7-5555-99',
            'status' => 'active',
        ]);

        // 2. Driver 1 submits offer -> rider notified
        $this->actingAs($this->driver);
        $offer1Res = $this->postJson(route('api.offers.store', $specialRequest->id), [
            'price_ils' => 80,
            'notes' => 'سيارة خاصة جاهزة',
        ]);
        $offer1Res->assertStatus(201);
        $offer1Id = $offer1Res->json('offer.id');

        $this->assertEquals(1, $this->rider->unreadNotifications()->count());
        $this->assertStringContainsString('عرض سعر جديد', $this->rider->unreadNotifications()->first()->data['title']);

        // 3. Driver 2 submits offer -> rider notified again
        $this->actingAs($otherDriver);
        $offer2Res = $this->postJson(route('api.offers.store', $specialRequest->id), [
            'price_ils' => 90,
            'notes' => 'تكييف ومقاعد مريحة',
        ]);
        $offer2Res->assertStatus(201);

        $this->assertEquals(2, $this->rider->unreadNotifications()->count());

        // 4. Rider accepts Driver 1's offer
        $this->actingAs($this->rider);
        $acceptRes = $this->postJson(route('api.offers.accept', ['id' => $specialRequest->id, 'offerId' => $offer1Id]));
        $acceptRes->assertStatus(200);

        // Winning driver (Driver 1) must receive congratulations notification
        $this->assertEquals(1, $this->driver->unreadNotifications()->count());
        $winNotification = $this->driver->unreadNotifications()->first();
        $this->assertStringContainsString('تم قبول عرضك', $winNotification->data['title']);

        // Losing driver (Driver 2) must receive polite closure notification
        $this->assertEquals(1, $otherDriver->unreadNotifications()->count());
        $loseNotification = $otherDriver->unreadNotifications()->first();
        $this->assertStringContainsString('تم إغلاق طلب المشوار الخاص', $loseNotification->data['title']);
    }

    /**
     * Test chat messaging notifies the other party.
     */
    public function test_chat_message_notifies_other_party(): void
    {
        $specialRequest = SpecialRequest::create([
            'rider_id' => $this->rider->id,
            'origin_name' => 'خانيونس',
            'destination_name' => 'دير البلح',
            'departure_date' => now()->addDay()->toDateString(),
            'departure_window' => 'عصراً',
            'seats_needed' => 2,
            'status' => 'open',
        ]);

        $offer = SpecialOffer::create([
            'request_id' => $specialRequest->id,
            'driver_id' => $this->driver->id,
            'price_ils' => 50,
            'status' => 'pending',
        ]);

        // Driver sends message -> Rider is notified
        $this->actingAs($this->driver);
        $sendRes = $this->postJson(route('api.chat.send', $offer->id), [
            'message' => 'مرحباً، هل نقطة الانطلاق قريبة من مستشفى ناصر؟',
        ]);
        $sendRes->assertStatus(201);

        $this->assertEquals(1, $this->rider->unreadNotifications()->count());
        $chatNotification = $this->rider->unreadNotifications()->first();
        $this->assertStringContainsString('رسالة جديدة من خليل السائق', $chatNotification->data['title']);
        $this->assertEquals('chat', $chatNotification->data['type']);
    }

    /**
     * Test rating submission notifies driver.
     */
    public function test_submitting_rating_notifies_driver(): void
    {
        $booking = Booking::create([
            'rider_id' => $this->rider->id,
            'route_id' => $this->route->id,
            'seats' => 1,
            'pickup_stop' => 'دوار العودة',
            'status' => 'completed',
        ]);

        $this->actingAs($this->rider);

        $rateRes = $this->postJson(route('api.trips.rate'), [
            'trip_type' => 'line',
            'trip_id' => $booking->id,
            'rating' => 5,
            'comment' => 'سائق ممتاز وملتزم بالموعد والهدوء أثناء القيادة',
        ]);

        $rateRes->assertStatus(201);

        $this->assertEquals(1, $this->driver->unreadNotifications()->count());
        $ratingNotif = $this->driver->unreadNotifications()->first();
        $this->assertStringContainsString('تقييم جديد', $ratingNotif->data['title']);
        $this->assertStringContainsString('5/5', $ratingNotif->data['title']);
        $this->assertEquals('rating', $ratingNotif->data['type']);
    }

    /**
     * Test map-pins endpoint returns ultra-lightweight pin payload for MapLibre.
     */
    public function test_map_pins_endpoint_returns_lightweight_pins(): void
    {
        $response = $this->getJson(route('api.rides.map_pins'));

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'ok',
                'count',
                'pins' => [
                    '*' => [
                        'vehicle_id',
                        'lat',
                        'lng',
                        'vehicle_type',
                        'vehicle_type_arabic',
                        'free_seats',
                        'route_name',
                        'last_landmark',
                        'position_age_arabic',
                    ],
                ],
            ]);

        $pins = $response->json('pins');
        $this->assertNotEmpty($pins);
        $first = $pins[0];
        $this->assertEquals($this->vehicle->id, $first['vehicle_id']);
        $this->assertArrayNotHasKey('photo', $first); // Lightweight: no heavy images
        $this->assertArrayNotHasKey('stops', $first); // Lightweight: no heavy stop geometries
    }

    /**
     * Test active vehicles endpoint supports native Laravel controller pagination.
     */
    public function test_active_vehicles_supports_native_controller_pagination(): void
    {
        $response = $this->getJson(route('api.rides.active', ['page' => 1, 'per_page' => 10]));

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 1)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('per_page', 10);
    }

    /**
     * Test notifications endpoint supports native controller pagination.
     */
    public function test_notifications_endpoint_supports_native_controller_pagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->rider->notify(new MishwarNotification(
                title: "إشعار رقم {$i}",
                message: "تفاصيل الإشعار رقم {$i}",
                type: 'general'
            ));
        }

        $this->actingAs($this->rider);

        $response = $this->getJson(route('api.notifications.index', ['page' => 1, 'per_page' => 2]));

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('total', 5)
            ->assertJsonPath('last_page', 3);

        $notifications = $response->json('notifications');
        $this->assertCount(2, $notifications);
    }

    /**
     * Test special requests and trip history endpoints support native controller pagination.
     */
    public function test_special_requests_and_trip_history_support_native_pagination(): void
    {
        // 1. Create 3 special requests
        for ($i = 1; $i <= 3; $i++) {
            SpecialRequest::create([
                'rider_id' => $this->rider->id,
                'origin_name' => "نقطة انطلاق {$i}",
                'destination_name' => "نقطة وصول {$i}",
                'departure_date' => now()->addDays($i)->toDateString(),
                'departure_window' => 'صباحاً',
                'seats_needed' => 2,
                'status' => 'open',
            ]);
        }

        $this->actingAs($this->rider);

        $reqRes = $this->getJson(route('api.requests.index', ['page' => 1, 'per_page' => 2]));
        $reqRes->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('total', 3)
            ->assertJsonPath('last_page', 2);
        $this->assertCount(2, $reqRes->json('requests'));

        // 2. Completed trip history pagination
        for ($i = 1; $i <= 3; $i++) {
            Booking::create([
                'rider_id' => $this->rider->id,
                'route_id' => $this->route->id,
                'seats' => 1,
                'pickup_stop' => 'دوار العودة',
                'status' => 'completed',
            ]);
        }

        $historyRes = $this->getJson(route('api.rider.history', ['page' => 1, 'per_page' => 2]));
        $historyRes->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('total', 3)
            ->assertJsonPath('last_page', 2);
        $this->assertCount(2, $historyRes->json('trips'));

        // 3. Driver offers pagination and driver history pagination
        $this->actingAs($this->driver);

        $driverReqRes = $this->getJson(route('api.requests.index', ['page' => 1, 'per_page' => 2, 'tab' => 'all']));
        $driverReqRes->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('total', 3)
            ->assertJsonPath('last_page', 2);
        $this->assertCount(2, $driverReqRes->json('requests'));

        $driverHistoryRes = $this->getJson(route('api.driver.history', ['page' => 1, 'per_page' => 2]));
        $driverHistoryRes->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('total', 3)
            ->assertJsonPath('last_page', 2);
        $this->assertCount(2, $driverHistoryRes->json('trips'));
    }
}
