<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\ChatMessage;
use App\Models\DriverRoute;
use App\Models\LocationShare;
use App\Models\SpecialOffer;
use App\Models\SpecialRequest;
use App\Models\TripRating;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SimulateMishwarCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meshwar:simulate 
                            {--vehicles=300 : Number of active vehicles to generate}
                            {--bookings=50 : Number of rider bookings to simulate}
                            {--requests=20 : Number of special charter requests to simulate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a high-density production simulation for Mishwar across the Gaza Strip with 300 active vehicles, live routes, bookings, and special requests';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $vehicleCount = (int) $this->option('vehicles');
        $bookingCount = (int) $this->option('bookings');
        $requestCount = (int) $this->option('requests');

        $this->info("🚀 Starting Mishwar Production Simulation ({$vehicleCount} vehicles, {$bookingCount} bookings, {$requestCount} special requests)...");

        // 1. Load verified OSM gazetteer
        $gazetteerPath = base_path('data/gaza-gazetteer.json');
        if (! File::exists($gazetteerPath)) {
            $this->error("Gazetteer not found at: {$gazetteerPath}");

            return self::FAILURE;
        }

        $landmarks = json_decode(File::get($gazetteerPath), true);
        if (empty($landmarks)) {
            $this->error('Gazetteer landmarks array is empty');

            return self::FAILURE;
        }

        $this->line('📍 Loaded '.count($landmarks).' verified OpenStreetMap Gaza landmarks.');

        // 2. Generate Active Drivers, Vehicles & Routes
        $this->info("🚗 Generating {$vehicleCount} active vehicles across Gaza...");
        $bar = $this->output->createProgressBar($vehicleCount);
        $bar->start();

        $firstNames = ['محمد', 'أحمد', 'خالد', 'محمود', 'إبراهيم', 'يوسف', 'عمر', 'بلال', 'طارق', 'حمزة', 'خليل', 'أنس', 'سعيد', 'رامي', 'وليد'];
        $lastNames = ['أبو شمالة', 'النجار', 'بربخ', 'الأغا', 'الشاعر', 'صيام', 'حلس', 'دغمش', 'القواسمي', 'الأسطل', 'أبو ركبة', 'عياش', 'أبو طه'];
        $vehicleTypes = [
            ['type' => 'car', 'seats' => 4, 'photo' => '/images/sample-car.svg'],
            ['type' => 'ajala', 'seats' => 10, 'photo' => '/images/sample-ajala.svg'],
            ['type' => 'bus', 'seats' => 14, 'photo' => '/images/sample-ajala.svg'],
        ];

        $driverUsers = [];
        $createdRoutes = [];

        DB::beginTransaction();
        try {
            for ($i = 1; $i <= $vehicleCount; $i++) {
                $driverName = $firstNames[array_rand($firstNames)].' '.$lastNames[array_rand($lastNames)]." ({$i})";
                $driverPhone = '059'.str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT);
                $driverEmail = "driver.sim.{$i}@meshwar.local";

                /** @var User $driver */
                $driver = User::updateOrCreate(
                    ['email' => $driverEmail],
                    [
                        'name' => $driverName,
                        'phone' => $driverPhone,
                        'role' => 'driver',
                        'avatar' => 'https://ui-avatars.com/api/?name='.urlencode($driverName).'&background=0D9488&color=fff',
                    ]
                );
                $driverUsers[] = $driver;

                // Pick vehicle configuration
                $vConfig = $vehicleTypes[$i % count($vehicleTypes)];
                Vehicle::updateOrCreate(
                    ['driver_id' => $driver->id],
                    [
                        'type' => $vConfig['type'],
                        'seat_count' => $vConfig['seats'],
                        'license_plate' => 'غزة - '.(1000 + $i),
                        'status' => 'active',
                        'photos' => [$vConfig['photo']],
                    ]
                );

                // Pick 3 to 5 contiguous ordered stops along corridor
                $sliceStart = rand(0, max(0, count($landmarks) - 5));
                $routeLandmarks = array_slice($landmarks, $sliceStart, rand(3, 5));

                $stops = [];
                $order = 1;
                $fare = 0;
                foreach ($routeLandmarks as $lm) {
                    $stops[] = [
                        'order' => $order++,
                        'id' => $lm['id'],
                        'name' => $lm['name_ar'],
                        'lat' => $lm['lat'],
                        'lng' => $lm['lng'],
                        'fare_ils' => $fare,
                    ];
                    $fare += rand(3, 6);
                }

                $activeStop = $stops[array_rand($stops)];
                // Small jitter within ~300 meters
                $latJitter = (mt_rand(-300, 300) / 100000.0);
                $lngJitter = (mt_rand(-300, 300) / 100000.0);
                $currentLat = round($activeStop['lat'] + $latJitter, 5);
                $currentLng = round($activeStop['lng'] + $lngJitter, 5);

                $routeName = $stops[0]['name'].' <-> '.$stops[count($stops) - 1]['name'];

                $route = DriverRoute::updateOrCreate(
                    ['driver_id' => $driver->id],
                    [
                        'name' => $routeName,
                        'start_point' => $stops[0]['name'],
                        'first_trip_time' => sprintf('%02d:%02d', rand(6, 17), rand(0, 5) * 10),
                        'status' => 'on_route',
                        'is_trip_active' => true,
                        'last_latitude' => $currentLat,
                        'last_longitude' => $currentLng,
                        'last_landmark' => $activeStop['name'],
                        'last_position_at' => now()->subMinutes(rand(1, 12)),
                        'stops' => $stops,
                    ]
                );
                $createdRoutes[] = $route;

                $bar->advance();
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed generating vehicles: '.$e->getMessage());

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Successfully generated {$vehicleCount} active drivers, vehicles, and live GPS positions.");

        // 3. Generate Simulated Riders, Bookings, Boarding & Completion
        $this->info("👥 Simulating {$bookingCount} passenger bookings with live location shares...");
        $bBar = $this->output->createProgressBar($bookingCount);
        $bBar->start();

        $riders = [];
        for ($rIdx = 1; $rIdx <= min(30, $bookingCount); $rIdx++) {
            $rName = 'راكب تجريبي ('.$rIdx.')';
            $rPhone = '056'.str_pad((string) (2000000 + $rIdx), 7, '0', STR_PAD_LEFT);
            $riders[] = User::updateOrCreate(
                ['email' => "rider.sim.{$rIdx}@meshwar.local"],
                [
                    'name' => $rName,
                    'phone' => $rPhone,
                    'role' => 'rider',
                ]
            );
        }

        DB::beginTransaction();
        try {
            for ($b = 1; $b <= $bookingCount; $b++) {
                $rider = $riders[array_rand($riders)];
                $route = $createdRoutes[array_rand($createdRoutes)];
                $stops = $route->stops ?? [];
                $pickup = $stops[0]['name'] ?? 'دوار العودة';
                $dropoff = $stops[count($stops) - 1]['name'] ?? 'ميدان فلسطين';

                // Status distribution: confirmed, boarded, completed
                $statuses = ['confirmed', 'confirmed', 'boarded', 'completed'];
                $status = $statuses[$b % count($statuses)];

                $booking = Booking::updateOrCreate(
                    ['client_mutation_id' => "sim-booking-{$b}"],
                    [
                        'rider_id' => $rider->id,
                        'route_id' => $route->id,
                        'seats' => rand(1, 2),
                        'pickup_stop' => $pickup,
                        'dropoff_stop' => $dropoff,
                        'fare_ils' => rand(5, 20),
                        'status' => $status,
                    ]
                );

                // For confirmed bookings: simulate location share
                if ($status === 'confirmed') {
                    $shareStatus = ($b % 2 === 0) ? 'active' : 'requested';
                    LocationShare::updateOrCreate(
                        ['booking_id' => $booking->id],
                        [
                            'rider_id' => $rider->id,
                            'driver_id' => $route->driver_id,
                            'status' => $shareStatus,
                            'last_latitude' => $shareStatus === 'active' ? round(31.3 + (rand(10, 200) / 1000.0), 5) : null,
                            'last_longitude' => $shareStatus === 'active' ? round(34.25 + (rand(10, 200) / 1000.0), 5) : null,
                            'last_shared_at' => $shareStatus === 'active' ? now()->subMinutes(rand(1, 8)) : null,
                            'expires_at' => now()->addMinutes(15),
                        ]
                    );
                }

                // For completed bookings: simulate rider 1-5 star rating
                if ($status === 'completed') {
                    $ratingVal = rand(4, 5);
                    TripRating::updateOrCreate(
                        ['client_mutation_id' => "sim-rating-{$b}"],
                        [
                            'rider_id' => $rider->id,
                            'driver_id' => $route->driver_id,
                            'booking_id' => $booking->id,
                            'rating' => $ratingVal,
                            'comment' => $ratingVal === 5 ? 'سائق ممتاز والتزام كامل بالموعد والمحطات' : 'رحلة جيدة وسيارة مريحة',
                        ]
                    );
                }

                $bBar->advance();
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed generating bookings: '.$e->getMessage());

            return self::FAILURE;
        }

        $bBar->finish();
        $this->newLine();
        $this->info("✅ Successfully simulated {$bookingCount} bookings, live location shares, and trip reviews.");

        // 4. Generate Simulated Special Charter Requests & Driver Bidding
        $this->info("🏷️ Simulating {$requestCount} special charter requests with driver price offers...");
        $sBar = $this->output->createProgressBar($requestCount);
        $sBar->start();

        DB::beginTransaction();
        try {
            for ($s = 1; $s <= $requestCount; $s++) {
                $rider = $riders[array_rand($riders)];
                $startLm = $landmarks[array_rand($landmarks)];
                $destLm = $landmarks[array_rand($landmarks)];

                $reqStatus = ($s % 3 === 0) ? 'accepted' : 'open';

                $specialReq = SpecialRequest::updateOrCreate(
                    ['client_mutation_id' => "sim-special-req-{$s}"],
                    [
                        'rider_id' => $rider->id,
                        'origin_name' => $startLm['name_ar'],
                        'destination_name' => $destLm['name_ar'],
                        'departure_date' => now()->addDays(rand(0, 2))->format('Y-m-d'),
                        'departure_window' => sprintf('%02d:00', rand(7, 18)),
                        'seats_needed' => rand(2, 5),
                        'notes' => 'مشوار خاص مع حقائب وأمتعة',
                        'status' => $reqStatus,
                    ]
                );

                // 2 competing driver offers per request
                $bidDriver1 = $driverUsers[($s * 2) % count($driverUsers)];
                $bidDriver2 = $driverUsers[(($s * 2) + 1) % count($driverUsers)];

                $offerPrice1 = rand(40, 90);
                $offerPrice2 = $offerPrice1 + rand(10, 25);

                $offer1 = SpecialOffer::updateOrCreate(
                    ['request_id' => $specialReq->id, 'driver_id' => $bidDriver1->id],
                    [
                        'price_ils' => $offerPrice1,
                        'notes' => 'سيارة جاهزة ومكيفة ومستعد للتحرك فوراً',
                        'status' => $reqStatus === 'accepted' ? 'accepted' : 'pending',
                        'client_mutation_id' => "sim-sp-off-{$s}-1",
                    ]
                );

                SpecialOffer::updateOrCreate(
                    ['request_id' => $specialReq->id, 'driver_id' => $bidDriver2->id],
                    [
                        'price_ils' => $offerPrice2,
                        'notes' => 'خدمة باب لباب مع مساعدة في الأمتعة',
                        'status' => $reqStatus === 'accepted' ? 'rejected' : 'pending',
                        'client_mutation_id' => "sim-sp-off-{$s}-2",
                    ]
                );

                if ($reqStatus === 'accepted') {
                    $specialReq->update(['accepted_offer_id' => $offer1->id]);

                    // Short chat exchange
                    ChatMessage::updateOrCreate(
                        ['client_mutation_id' => "sim-chat-{$s}-1"],
                        [
                            'offer_id' => $offer1->id,
                            'sender_id' => $rider->id,
                            'message' => 'مرحباً، هل يمكن المرور بنقطة توقف إضافية على الطريق؟',
                        ]
                    );

                    ChatMessage::updateOrCreate(
                        ['client_mutation_id' => "sim-chat-{$s}-2"],
                        [
                            'offer_id' => $offer1->id,
                            'sender_id' => $bidDriver1->id,
                            'message' => 'أهلاً بك يا أخي، نعم بكل سرور بدون أي مشكلة.',
                        ]
                    );
                }

                $sBar->advance();
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed generating special requests: '.$e->getMessage());

            return self::FAILURE;
        }

        $sBar->finish();
        $this->newLine();
        $this->info("✅ Successfully simulated {$requestCount} special requests, price bidding, and direct chats.");

        $this->newLine();
        $this->info('🎉 Simulation environment successfully established and active in the database!');
        $this->line("• Active Vehicles on Gaza Map: {$vehicleCount}");
        $this->line("• Transit Bookings & Location Shares: {$bookingCount}");
        $this->line("• Special Requests & Driver Bids: {$requestCount}");

        return self::SUCCESS;
    }
}
