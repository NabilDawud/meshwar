<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\DriverRoute;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Redirect user to Google OAuth consent screen.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the OAuth callback from Google.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            return redirect()->route('login')->withErrors([
                'auth' => 'فشل تسجيل الدخول باستخدام Google، يرجى المحاولة مرة أخرى.',
            ]);
        }

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            $user->update([
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar() ?? $user->avatar,
            ]);
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?? 'مستخدم مشوار',
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'role' => null,
            ]);
        }

        Auth::login($user, remember: true);

        return $this->redirectBasedOnRole($user);
    }

    /**
     * Local development & demo mock login to simulate Google OAuth without network requests.
     * Enabled in local/testing, or when QA_MODE/DEMO_MODE=1, or when Google credentials are not yet configured.
     */
    public function mockLogin(Request $request): RedirectResponse
    {
        $hasGoogleConfig = ! empty(config('services.google.client_id')) && ! empty(config('services.google.client_secret'));

        if (! app()->environment('local', 'testing') && env('QA_MODE') !== '1' && env('DEMO_MODE') !== '1' && $hasGoogleConfig) {
            abort(404);
        }

        $role = $request->query('role', 'new'); // 'rider' | 'driver' | 'new'

        $email = $role === 'new'
            ? 'new-user-'.uniqid().'@example.com'
            : ($role === 'driver' ? 'demo-driver@meshwar.local' : 'demo-rider@meshwar.local');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $role === 'driver' ? 'أحمد السائق' : ($role === 'rider' ? 'محمود الراكب' : 'مستخدم جديد'),
                'phone' => $role === 'driver' ? '0599123456' : ($role === 'rider' ? '0599654321' : null),
                'google_id' => 'mock-google-'.md5($email),
                'avatar' => 'https://ui-avatars.com/api/?name=User&background=0D9488&color=fff',
                'role' => $role === 'new' ? null : $role,
            ]
        );

        // If driver user was freshly created in mock, ensure driver has an active vehicle and route
        if ($role === 'driver' && ! $user->vehicle) {
            Vehicle::create([
                'driver_id' => $user->id,
                'type' => 'car',
                'seat_count' => 4,
                'license_plate' => 'غزة - 1020',
                'status' => 'active',
                'photos' => ['/images/sample-car.svg'],
            ]);

            DriverRoute::create([
                'driver_id' => $user->id,
                'name' => 'غزة (الرمال) <-> خانيونس (البلد)',
                'start_point' => 'ميدان فلسطين - غزة',
                'first_trip_time' => '07:00',
                'status' => 'on_route',
                'is_trip_active' => true,
                'last_latitude' => 31.5019,
                'last_longitude' => 34.4668,
                'last_landmark' => 'ميدان فلسطين',
                'last_position_at' => now(),
                'stops' => [
                    ['name' => 'ميدان فلسطين', 'lat' => 31.5019, 'lng' => 34.4668, 'order' => 1, 'fare_ils' => 0],
                    ['name' => 'دوار النصيرات', 'lat' => 31.4485, 'lng' => 34.3912, 'order' => 2, 'fare_ils' => 6],
                    ['name' => 'دوار بني سهيلا', 'lat' => 31.3456, 'lng' => 34.3182, 'order' => 3, 'fare_ils' => 12],
                ],
            ]);
        }

        Auth::login($user, remember: true);

        return $this->redirectBasedOnRole($user);
    }

    /**
     * Determine next screen based on user role state.
     */
    protected function redirectBasedOnRole(User $user): RedirectResponse
    {
        if (empty($user->role)) {
            return redirect()->route('role.choice');
        }

        return $user->role === 'driver'
            ? redirect()->route('driver.dashboard')
            : redirect()->route('rider.home');
    }
}
