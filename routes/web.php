<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RoleChoiceController;
use App\Http\Controllers\Driver\DriverRouteController;
use App\Http\Controllers\Driver\DriverVehicleController;
use App\Http\Controllers\GazetteerController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Guest Access Home Screen
Route::get('/', function () {
    return view('welcome');
})->name('home');

// Google Socialite Authentication
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::get('/auth/google/mock', [GoogleAuthController::class, 'mockLogin'])->name('auth.google.mock');

// First-Time Role Choice
Route::middleware('auth')->group(function () {
    Route::get('/auth/role-choice', [RoleChoiceController::class, 'show'])->name('role.choice');
    Route::post('/auth/role-choice', [RoleChoiceController::class, 'store'])->name('role.choice.store');

    // Role-specific landing destinations
    Route::get('/rides', function () {
        return view('rider.home');
    })->name('rider.home');

    Route::get('/driver', function () {
        /** @var User $user */
        $user = Auth::user();
        $vehicle = $user->vehicle;
        $route = $user->driverRoute;

        return view('driver.dashboard', compact('vehicle', 'route'));
    })->name('driver.dashboard');

    Route::get('/driver/vehicle', function () {
        /** @var User $user */
        $user = Auth::user();
        $vehicle = $user->vehicle;

        return view('driver.vehicle', compact('vehicle'));
    })->name('driver.vehicle');

    Route::get('/driver/route', function () {
        /** @var User $user */
        $user = Auth::user();
        $route = $user->driverRoute;

        return view('driver.route', compact('route'));
    })->name('driver.route');

    // Driver Vehicle & Route APIs
    Route::get('/api/driver/vehicle', [DriverVehicleController::class, 'show'])->name('api.driver.vehicle.show');
    Route::post('/api/driver/vehicle', [DriverVehicleController::class, 'store'])->name('api.driver.vehicle.store');
    Route::get('/api/driver/route', [DriverRouteController::class, 'show'])->name('api.driver.route.show');
    Route::post('/api/driver/route', [DriverRouteController::class, 'store'])->name('api.driver.route.store');
    Route::post('/api/driver/route/status', [DriverRouteController::class, 'updateStatus'])->name('api.driver.route.status');
});

// Verified OpenStreetMap Gaza Gazetteer
Route::get('/api/gazetteer', [GazetteerController::class, 'index'])->name('api.gazetteer');

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
    if (env('QA_MODE') !== '1') {
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
