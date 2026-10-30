<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RoleChoiceController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Driver\DriverRouteController;
use App\Http\Controllers\Driver\DriverScheduleController;
use App\Http\Controllers\Driver\DriverVehicleController;
use App\Http\Controllers\GazetteerController;
use App\Http\Controllers\LocationShareController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Rides\ActiveVehicleController;
use App\Http\Controllers\Rides\TripPlannerController;
use App\Http\Controllers\SpecialOfferController;
use App\Http\Controllers\SpecialRequestController;
use App\Http\Controllers\TripHistoryController;
use App\Http\Controllers\TripRatingController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Rider Home Screen (Accessible to Guests and Authenticated Riders)
Route::get('/', function () {
    return view('rider.home');
})->name('home');

Route::get('/rides', function () {
    return view('rider.home');
})->name('rider.home');

// Google Socialite Authentication
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::get('/auth/google/mock', [GoogleAuthController::class, 'mockLogin'])->name('auth.google.mock');

// First-Time Role Choice & Profile
Route::middleware('auth')->group(function () {
    Route::get('/auth/role-choice', [RoleChoiceController::class, 'show'])->name('role.choice');
    Route::post('/auth/role-choice', [RoleChoiceController::class, 'store'])->name('role.choice.store');

    // User Profile & Settings
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/driver', function () {
        /** @var User $user */
        $user = Auth::user();

        if ($user->role !== 'driver') {
            abort(403, 'غير مصرح - حساب السائق مطلوب');
        }

        $user->load(['vehicle', 'driverRoutes', 'schedules.route']);
        $vehicle = $user->vehicle;
        $driverRoutes = $user->driverRoutes;
        $todaySchedule = collect(DriverScheduleController::getDriverWeeklySchedule($user, $user->schedules))->firstWhere('day_of_week', now()->dayOfWeek);
        $todayRoute = null;
        if ($todaySchedule && ! empty($todaySchedule['route_id'])) {
            $todayRoute = $driverRoutes->firstWhere('id', $todaySchedule['route_id']);
        }
        $route = $todayRoute ?? ($driverRoutes->firstWhere('is_active', true) ?? $driverRoutes->first());
        $schedules = DriverScheduleController::getDriverWeeklySchedule($user, $user->schedules, $route);

        return view('driver.dashboard', compact('vehicle', 'route', 'driverRoutes', 'schedules', 'todaySchedule'));
    })->name('driver.dashboard');

    Route::get('/driver/schedule', [DriverScheduleController::class, 'showView'])->name('driver.schedule');

    Route::get('/driver/vehicle', function () {
        /** @var User $user */
        $user = Auth::user();

        if ($user->role !== 'driver') {
            abort(403, 'غير مصرح - حساب السائق مطلوب');
        }

        $vehicle = $user->vehicle;

        return view('driver.vehicle', compact('vehicle'));
    })->name('driver.vehicle');

    Route::get('/driver/route', function (Request $request) {
        /** @var User $user */
        $user = Auth::user();

        if ($user->role !== 'driver') {
            abort(403, 'غير مصرح - حساب السائق مطلوب');
        }

        $allRoutes = $user->driverRoutes()->orderBy('id', 'asc')->get();

        $routeId = $request->query('route_id');
        if ($request->has('new')) {
            $route = null;
        } elseif ($routeId) {
            $route = $allRoutes->firstWhere('id', (int) $routeId) ?? $allRoutes->first();
        } else {
            $route = $allRoutes->firstWhere('is_active', true) ?? $allRoutes->first();
        }

        return view('driver.route', compact('route', 'allRoutes'));
    })->name('driver.route');

    // Driver Vehicle & Route APIs
    Route::get('/api/driver/vehicle', [DriverVehicleController::class, 'show'])->name('api.driver.vehicle.show');
    Route::post('/api/driver/vehicle', [DriverVehicleController::class, 'store'])->name('api.driver.vehicle.store');
    Route::get('/api/driver/routes', [DriverRouteController::class, 'index'])->name('api.driver.routes.index');
    Route::post('/api/driver/routes/{id}/primary', [DriverRouteController::class, 'makePrimary'])->name('api.driver.routes.primary');
    Route::delete('/api/driver/routes/{id}', [DriverRouteController::class, 'destroy'])->name('api.driver.routes.destroy');
    Route::get('/api/driver/route', [DriverRouteController::class, 'show'])->name('api.driver.route.show');
    Route::post('/api/driver/route', [DriverRouteController::class, 'store'])->name('api.driver.route.store');
    Route::post('/api/driver/route/status', [DriverRouteController::class, 'updateStatus'])->name('api.driver.route.status');
    Route::post('/api/driver/route/direction', [DriverRouteController::class, 'updateDirection'])->name('api.driver.route.direction');
    Route::post('/api/driver/route/two-way', [DriverRouteController::class, 'updateTwoWay'])->name('api.driver.route.two_way');
    Route::post('/api/driver/position', [DriverRouteController::class, 'updatePosition'])->name('api.driver.position');

    // Driver Weekly Schedules APIs
    Route::get('/api/driver/schedules', [DriverScheduleController::class, 'index'])->name('api.driver.schedules.index');
    Route::post('/api/driver/schedules', [DriverScheduleController::class, 'store'])->name('api.driver.schedules.store');

    // Pre-Trip Driver Manifest View & APIs
    Route::get('/driver/manifest', function () {
        /** @var User $user */
        $user = Auth::user();

        if ($user->role !== 'driver') {
            abort(403, 'غير مصرح - حساب السائق مطلوب');
        }

        return view('driver.manifest');
    })->name('driver.manifest');
    Route::get('/api/driver/manifest', [BookingController::class, 'driverManifest'])->name('api.driver.manifest');
    Route::post('/api/driver/bookings/{id}/cancel', [BookingController::class, 'cancelByDriver'])->name('api.driver.bookings.cancel');
    Route::post('/api/driver/bookings/{id}/confirm', [BookingController::class, 'confirmByDriver'])->name('api.driver.bookings.confirm');

    // Rider Bookings View & APIs
    Route::get('/bookings', [BookingController::class, 'riderBookingsPage'])->name('rider.bookings');
    Route::get('/api/rider/bookings', [BookingController::class, 'riderBookings'])->name('api.rider.bookings');
    Route::post('/api/bookings', [BookingController::class, 'store'])->name('api.bookings.store');

    // Live Location Sharing APIs
    Route::post('/api/location-shares/request', [LocationShareController::class, 'requestShare'])->name('api.location.request');
    Route::post('/api/location-shares/{id}/respond', [LocationShareController::class, 'respond'])->name('api.location.respond');
    Route::post('/api/location-shares/{id}/update', [LocationShareController::class, 'updatePosition'])->name('api.location.update');
    Route::post('/api/location-shares/{id}/end', [LocationShareController::class, 'end'])->name('api.location.end');

    // Milestone 6: Special Requests (مراسلة), Offers & Direct Chat
    Route::get('/requests', function () {
        return view('rides.requests');
    })->name('rides.requests');
    Route::get('/requests/{id}', function ($id) {
        return view('rides.request_detail', compact('id'));
    })->name('rides.request_detail');

    Route::get('/driver/offers', function () {
        /** @var User $user */
        $user = Auth::user();

        if ($user->role !== 'driver') {
            abort(403, 'غير مصرح - حساب السائق مطلوب');
        }

        return view('driver.offers');
    })->name('driver.offers');

    Route::get('/chat/{offer_id}', function ($offer_id) {
        return view('chat.thread', compact('offer_id'));
    })->name('chat.thread');

    // Special Requests & Offers APIs
    Route::get('/api/requests', [SpecialRequestController::class, 'index'])->name('api.requests.index');
    Route::post('/api/requests', [SpecialRequestController::class, 'store'])->name('api.requests.store');
    Route::get('/api/requests/{id}', [SpecialRequestController::class, 'show'])->name('api.requests.show');
    Route::post('/api/requests/{id}/offers', [SpecialOfferController::class, 'store'])->name('api.offers.store');
    Route::post('/api/requests/{id}/offers/{offerId}/accept', [SpecialRequestController::class, 'acceptOffer'])->name('api.offers.accept');

    // Chat APIs
    Route::get('/api/chat/{offer_id}', [ChatController::class, 'getMessages'])->name('api.chat.messages');
    Route::post('/api/chat/{offer_id}', [ChatController::class, 'sendMessage'])->name('api.chat.send');

    // Milestone 7: Trip History for Both Roles & 1-5 Star Ratings
    Route::get('/trips', function () {
        return view('rides.history');
    })->name('rides.history');

    Route::get('/driver/history', function () {
        /** @var User $user */
        $user = Auth::user();

        if ($user->role !== 'driver') {
            abort(403, 'غير مصرح - حساب السائق مطلوب');
        }

        return view('driver.history');
    })->name('driver.history');

    Route::get('/api/rider/history', [TripHistoryController::class, 'riderHistory'])->name('api.rider.history');
    Route::get('/api/driver/history', [TripHistoryController::class, 'driverHistory'])->name('api.driver.history');
    Route::post('/api/trips/rate', [TripRatingController::class, 'store'])->name('api.trips.rate');
    Route::post('/api/bookings/{id}/complete', [BookingController::class, 'completeBooking'])->name('api.bookings.complete');

    // Notification APIs
    Route::get('/api/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
    Route::post('/api/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('api.notifications.read');
    Route::post('/api/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('api.notifications.read_all');
    Route::post('/api/requests/{id}/complete', [SpecialRequestController::class, 'complete'])->name('api.requests.complete');
});

// Verified OpenStreetMap Gaza Gazetteer
Route::get('/api/gazetteer', [GazetteerController::class, 'index'])->name('api.gazetteer');

// Active Transit Vehicles & Search (Open to Guests and Riders)
Route::get('/api/rides/active-vehicles', [ActiveVehicleController::class, 'index'])->name('api.rides.active');
Route::get('/api/rides/map-pins', [ActiveVehicleController::class, 'mapPins'])->name('api.rides.map_pins');

// Trip Planner View & Offline Graph APIs (Open to Guests and Riders)
Route::get('/plan', function () {
    return view('rides.plan');
})->name('rides.plan');
Route::get('/api/rides/plan', [TripPlannerController::class, 'plan'])->name('api.rides.plan');
Route::get('/api/routes/graph', [TripPlannerController::class, 'graph'])->name('api.routes.graph');

/*
|--------------------------------------------------------------------------
| API & Contract Routes
|--------------------------------------------------------------------------
*/

// Health Check Route
Route::get('/api/health', function () {
    return response()->json([
        'ok' => true,
        'commit' => env('GIT_REV', 'm1-dev'),
    ]);
})->name('api.health');

// QA Login Endpoint (Zero-credential sign in for testing)
Route::get('/api/qa-login', function (Request $request) {
    if (config('app.qa_mode', env('QA_MODE')) !== '1') {
        abort(404);
    }

    $role = $request->query('role') ?? $request->query('as');
    if (! in_array($role, ['driver', 'rider'])) {
        return response()->json(['error' => 'Invalid role. Use ?as=driver or ?as=rider'], 400);
    }

    $email = "qa-{$role}@meshwar.local";
    $user = User::firstOrCreate(
        ['email' => $email],
        [
            'name' => $role === 'driver' ? 'سائق تجريبي (QA)' : 'راكب تجريبي (QA)',
            'role' => $role,
            'google_id' => 'qa-google-'.md5($email),
            'avatar' => 'https://ui-avatars.com/api/?name=QA&background=0D9488&color=fff',
        ]
    );

    // Ensure role matches requested role
    if ($user->role !== $role) {
        $user->update(['role' => $role]);
    }

    Auth::login($user);

    return response()->json([
        'ok' => true,
        'role' => $role,
        'user_id' => $user->id,
        'name' => $user->name,
    ]);
})->name('api.qa-login');
