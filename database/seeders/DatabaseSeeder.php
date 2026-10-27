<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\DriverRoute;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with a realistic driver, 10-seat vehicle, and real Gaza route.
     */
    public function run(): void
    {
        // 1. Seed Demo Driver User
        $driver = User::updateOrCreate(
            ['email' => 'driver@meshwar.local'],
            [
                'name' => 'أبو أحمد السائق',
                'phone' => '0599123456',
                'role' => 'driver',
                'google_id' => 'seed-driver-google-1',
                'avatar' => 'https://ui-avatars.com/api/?name=Abu+Ahmed&background=0D9488&color=fff',
            ]
        );

        // 2. Seed Demo Rider User
        User::updateOrCreate(
            ['email' => 'rider@meshwar.local'],
            [
                'name' => 'محمود الراكب',
                'phone' => '0569876543',
                'role' => 'rider',
                'google_id' => 'seed-rider-google-1',
                'avatar' => 'https://ui-avatars.com/api/?name=Mahmoud&background=0D9488&color=fff',
            ]
        );

        // 3. Seed 10-seat 'Ajala Vehicle with Exterior Street Recognition Photo
        Vehicle::updateOrCreate(
            ['driver_id' => $driver->id],
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

        // 4. Seed Real Route along Salah al-Din Corridor with Ordered Stops and Leg Fares
        $route = DriverRoute::updateOrCreate(
            ['driver_id' => $driver->id],
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

        // 5. Seed Real Confirmed Booking (Reflecting Driver Manifest & Pre-Trip State)
        $rider = User::where('email', 'rider@meshwar.local')->first();
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
    }
}
