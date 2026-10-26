<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RoleChoiceController;
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
        return view('driver.dashboard');
    })->name('driver.dashboard');
});

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
