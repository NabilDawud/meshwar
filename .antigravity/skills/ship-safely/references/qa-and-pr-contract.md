# QA Registry & PR Technical Contract (Laravel)

## 1. QA Routes Manifest (`qa/routes.txt`)
Format: `<role> <relative-path> <identifier>`
Example:
```text
guest / LandingScreen
guest /login LoginScreen
rider /rides AvailableRides
rider /requests/new NewRideRequest
rider /requests MyRideRequests
driver /route/edit DriverDailyRoute
driver /offers DriverOfferBoard
```

## 2. Zero-Credential QA Authentication Endpoint (Laravel)
```php
// routes/web.php or routes/api.php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

Route::get('/api/qa-login', function (Request $request) {
    if (env('QA_MODE') !== '1') {
        abort(404);
    }

    $role = $request->query('role');
    if (!in_array($role, ['driver', 'rider'])) {
        return response()->json(['error' => 'Invalid role'], 400);
    }

    // Find or create demo user for role
    $user = User::firstOrCreate(
        ['email' => "demo-{$role}@meshwar.local"],
        [
            'name' => $role === 'driver' ? 'سائق تجريبي' : 'راكب تجريبي',
            'password' => bcrypt('qa-password-123'),
            'role' => $role,
        ]
    );

    Auth::login($user);

    return response()->json([
        'ok' => true,
        'role' => $role,
        'user_id' => $user->id,
    ]);
});
```

## 3. Pull Request Template
```markdown
### What can a user now do?
- Plain-language description of the newly active feature.

### What was cut or deferred?
- Explicit list of features left out to ship this milestone safely.

### How to verify
1. Run `QA_MODE=1 php artisan serve`
2. Open `GET /api/qa-login?role=rider`
3. Visit `/rides` to see available routes.
```
