<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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
     * Local development mock login to simulate Google OAuth without network requests.
     */
    public function mockLogin(Request $request): RedirectResponse
    {
        if (! app()->environment('local', 'testing')) {
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
                'google_id' => 'mock-google-'.md5($email),
                'avatar' => 'https://ui-avatars.com/api/?name=User&background=0D9488&color=fff',
                'role' => $role === 'new' ? null : $role,
            ]
        );

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
