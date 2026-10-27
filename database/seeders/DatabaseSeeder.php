<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\ChatMessage;
use App\Models\DriverRoute;
use App\Models\SpecialOffer;
use App\Models\SpecialRequest;
use App\Models\TripRating;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with:
     * - Realistic drivers & vehicles (including 10-seat 'ajala & private car)
     * - Real Gaza transit route with 5 ordered stops
     * - Confirmed rider booking
     * - 1 open special request with 2 priced offers and a short chat thread
     */
    public function run(): void
    {
        // 1. Seed Demo Driver 1 (Abu Ahmed)
        $driver1 = User::updateOrCreate(
            ['email' => 'driver@meshwar.local'],
            [
                'name' => 'أبو أحمد السائق',
                'phone' => '0599123456',
                'role' => 'driver',
                'google_id' => 'seed-driver-google-1',
                'avatar' => 'https://ui-avatars.com/api/?name=Abu+Ahmed&background=0D9488&color=fff',
            ]
        );

        // 2. Seed Demo Driver 2 (Khaled - Private Car)
        $driver2 = User::updateOrCreate(
            ['email' => 'driver2@meshwar.local'],
            [
                'name' => 'خالد السائق (سيارة خاصة)',
                'phone' => '0599654321',
                'role' => 'driver',
                'google_id' => 'seed-driver-google-2',
                'avatar' => 'https://ui-avatars.com/api/?name=Khaled&background=2563EB&color=fff',
            ]
        );

        // 3. Seed Demo Rider User (Mahmoud)
        $rider = User::updateOrCreate(
            ['email' => 'rider@meshwar.local'],
            [
                'name' => 'محمود الراكب',
                'phone' => '0569876543',
                'role' => 'rider',
                'google_id' => 'seed-rider-google-1',
                'avatar' => 'https://ui-avatars.com/api/?name=Mahmoud&background=0D9488&color=fff',
            ]
        );

        // 4. Seed 10-seat 'Ajala Vehicle for Driver 1
        Vehicle::updateOrCreate(
            ['driver_id' => $driver1->id],
            [
                'type' => 'ajala',
                'seat_count' => 10,
                'license_plate' => 'غزة - 7890',
                'status' => 'active',
                'photos' => [
                    '/images/sample-ajala.svg',
                ],
            ]
        );

        // 5. Seed Private Sedan Vehicle for Driver 2
        Vehicle::updateOrCreate(
            ['driver_id' => $driver2->id],
            [
                'type' => 'car',
                'seat_count' => 4,
                'license_plate' => 'غزة - 4567',
                'status' => 'active',
                'photos' => [
                    '/images/sample-car.svg',
                ],
            ]
        );

        // 6. Seed Real Route along Salah al-Din Corridor with Ordered Stops and Leg Fares
        $route = DriverRoute::updateOrCreate(
            ['driver_id' => $driver1->id],
            [
                'name' => 'رفح <-> غزة (عبر طريق صلاح الدين)',
                'start_point' => 'دوار العودة - وسط رفح',
                'first_trip_time' => '06:30',
                'status' => 'on_route',
                'is_trip_active' => true,
                'last_latitude' => 31.3456,
                'last_longitude' => 34.3182,
                'last_landmark' => 'دوار بني سهيلا',
                'last_position_at' => now()->subMinutes(3),
                'stops' => [
                    [
                        'order' => 1,
                        'id' => 'rafah_al_awda',
                        'name' => 'دوار العودة - وسط رفح',
                        'lat' => 31.2828,
                        'lng' => 34.2536,
                        'fare_ils' => 0,
                    ],
                    [
                        'order' => 2,
                        'id' => 'khan_younis_bani_suheila',
                        'name' => 'دوار بني سهيلا',
                        'lat' => 31.3456,
                        'lng' => 34.3182,
                        'fare_ils' => 5,
                    ],
                    [
                        'order' => 3,
                        'id' => 'deir_al_balah_al_baraka',
                        'name' => 'دوار البركة - دير البلح',
                        'lat' => 31.4170,
                        'lng' => 34.3510,
                        'fare_ils' => 10,
                    ],
                    [
                        'order' => 4,
                        'id' => 'gaza_kuwait_roundabout',
                        'name' => 'دوار الكويت - جنوب غزة',
                        'lat' => 31.4920,
                        'lng' => 34.4550,
                        'fare_ils' => 15,
                    ],
                    [
                        'order' => 5,
                        'id' => 'gaza_palestine_square',
                        'name' => 'ميدان فلسطين (الساحة)',
                        'lat' => 31.5080,
                        'lng' => 34.4630,
                        'fare_ils' => 20,
                    ],
                ],
            ]
        );

        // 7. Seed Real Confirmed Booking (Reflecting Driver Manifest & Pre-Trip State)
        if ($rider && $route) {
            Booking::updateOrCreate(
                [
                    'rider_id' => $rider->id,
                    'route_id' => $route->id,
                ],
                [
                    'seats' => 1,
                    'pickup_stop' => 'دوار بني سهيلا',
                    'dropoff_stop' => 'ميدان فلسطين (الساحة)',
                    'fare_ils' => 15.00,
                    'status' => 'confirmed',
                    'client_mutation_id' => 'seed-mut-booking-1',
                ]
            );
        }

        // 8. Seed Milestone 6: Open Special Request with 2 Driver Offers and a Short Chat
        $specialRequest = SpecialRequest::updateOrCreate(
            ['client_mutation_id' => 'seed-mut-special-req-1'],
            [
                'rider_id' => $rider->id,
                'origin_name' => 'دوار العودة - وسط رفح',
                'destination_name' => 'ميدان فلسطين (الساحة) - غزة',
                'departure_date' => now()->format('Y-m-d'),
                'departure_window' => '08:00 صباحاً',
                'seats_needed' => 3,
                'notes' => 'مشوار خاص لعائلة مع 3 حقائب كبيرة، يفضل سيارة مريحة ومكيفة',
                'status' => 'open',
            ]
        );

        // Driver 1 Offer (Abu Ahmed at 70 ILS)
        $offer1 = SpecialOffer::updateOrCreate(
            [
                'request_id' => $specialRequest->id,
                'driver_id' => $driver1->id,
            ],
            [
                'price_ils' => 70.00,
                'notes' => 'جاهز للانطلاق الساعة 08:00، سيارة واسعة ومريحة لجميع الحقائب',
                'status' => 'pending',
                'client_mutation_id' => 'seed-mut-offer-1',
            ]
        );

        // Driver 2 Offer (Khaled at 85 ILS)
        SpecialOffer::updateOrCreate(
            [
                'request_id' => $specialRequest->id,
                'driver_id' => $driver2->id,
            ],
            [
                'price_ils' => 85.00,
                'notes' => 'سيارة خاصة حديثة ومكيفة، خدمة من الباب للباب',
                'status' => 'pending',
                'client_mutation_id' => 'seed-mut-offer-2',
            ]
        );

        // Short Chat Thread on Offer 1 (Mahmoud & Abu Ahmed)
        ChatMessage::updateOrCreate(
            ['client_mutation_id' => 'seed-mut-chat-1'],
            [
                'offer_id' => $offer1->id,
                'sender_id' => $rider->id,
                'message' => 'السلام عليكم يا أبو أحمد، هل يوجد متسع لـ 3 حقائب كبيرة في السيارة؟',
                'created_at' => now()->subMinutes(15),
            ]
        );

        ChatMessage::updateOrCreate(
            ['client_mutation_id' => 'seed-mut-chat-2'],
            [
                'offer_id' => $offer1->id,
                'sender_id' => $driver1->id,
                'message' => 'وعليكم السلام يا غالي، نعم الصندوق واسع ويتسع لجميع الحقائب براحة تامة.',
                'created_at' => now()->subMinutes(10),
            ]
        );

        ChatMessage::updateOrCreate(
            ['client_mutation_id' => 'seed-mut-chat-3'],
            [
                'offer_id' => $offer1->id,
                'sender_id' => $rider->id,
                'message' => 'تمام ممتاز، شكراً جزيلاً لك.',
                'created_at' => now()->subMinutes(5),
            ]
        );

        // 9. Seed Milestone 7: Completed Transit Booking with 5-Star Rating
        $completedBooking = Booking::updateOrCreate(
            ['client_mutation_id' => 'seed-mut-booking-completed-1'],
            [
                'rider_id' => $rider->id,
                'route_id' => $route->id,
                'seats' => 2,
                'pickup_stop' => 'دوار العودة - وسط رفح',
                'dropoff_stop' => 'دوار بني سهيلا',
                'fare_ils' => 10.00,
                'status' => 'completed',
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]
        );

        TripRating::updateOrCreate(
            ['client_mutation_id' => 'seed-mut-rating-1'],
            [
                'rider_id' => $rider->id,
                'driver_id' => $driver1->id,
                'booking_id' => $completedBooking->id,
                'rating' => 5,
                'comment' => 'سائق ممتاز وملتزم بالموعد والسيارة نظيفة جداً',
                'created_at' => now()->subDays(2),
            ]
        );

        // 10. Seed Milestone 7: Completed Special Charter Trip with 4-Star Rating
        $completedSpecial = SpecialRequest::updateOrCreate(
            ['client_mutation_id' => 'seed-mut-special-completed-1'],
            [
                'rider_id' => $rider->id,
                'origin_name' => 'دوار النجمة - رفح',
                'destination_name' => 'دوار الشهداء - خان يونس',
                'departure_date' => now()->subDay()->format('Y-m-d'),
                'departure_window' => '10:00 صباحاً',
                'seats_needed' => 2,
                'notes' => 'مشوار عائلي سريع',
                'status' => 'completed',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ]
        );

        $completedSpecialOffer = SpecialOffer::updateOrCreate(
            [
                'request_id' => $completedSpecial->id,
                'driver_id' => $driver2->id,
            ],
            [
                'price_ils' => 45.00,
                'notes' => 'سيارة خاصة مكيفة وجاهز فوراً',
                'status' => 'accepted',
                'client_mutation_id' => 'seed-mut-special-off-completed-1',
            ]
        );

        $completedSpecial->update(['accepted_offer_id' => $completedSpecialOffer->id]);

        TripRating::updateOrCreate(
            ['client_mutation_id' => 'seed-mut-rating-2'],
            [
                'rider_id' => $rider->id,
                'driver_id' => $driver2->id,
                'special_request_id' => $completedSpecial->id,
                'rating' => 4,
                'comment' => 'خدمة ممتازة وسائق مهذب وسريع في الوصول',
                'created_at' => now()->subDay(),
            ]
        );

        // 11. Seed Milestone 7: Completed Booking without a Rating (Ready to be rated by rider)
        Booking::updateOrCreate(
            ['client_mutation_id' => 'seed-mut-booking-completed-unrated'],
            [
                'rider_id' => $rider->id,
                'route_id' => $route->id,
                'seats' => 1,
                'pickup_stop' => 'دوار البركة - دير البلح',
                'dropoff_stop' => 'ميدان فلسطين (الساحة)',
                'fare_ils' => 15.00,
                'status' => 'completed',
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subHours(2),
            ]
        );
    }
}
