<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\SpecialOffer;
use App\Models\SpecialRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Show the user's profile and edit form.
     */
    public function show(): View
    {
        /** @var User $user */
        $user = Auth::user();

        return view('profile.show', compact('user'));
    }

    /**
     * Update the user's name and mobile phone number.
     *
     * @throws ValidationException
     */
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Safety enforcement: cannot change identity or phone during an active in-progress trip
        if ($this->hasActiveTrip($user)) {
            throw ValidationException::withMessages([
                'profile' => 'لا يمكن تعديل البيانات الشخصية أثناء وجود رحلة جارية، لضمان سلامة وتنسيق المشوار.',
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:60'],
            'phone' => ['required', 'string', 'regex:/^(\+?(970|972)|0)?5[69]\d{7}$/'],
        ], [
            'name.required' => 'الاسم مطلوب.',
            'name.min' => 'يجب ألا يقل الاسم عن 3 أحرف.',
            'name.max' => 'يجب ألا يتجاوز الاسم 60 حرفاً.',
            'phone.required' => 'رقم الجوال مطلوب للتواصل وتنسيق الرحلات.',
            'phone.regex' => 'يرجى إدخال رقم جوال فلسطيني صحيح (مثال: 0599123456 أو 0569123456).',
        ]);

        $cleanName = trim(strip_tags($validated['name']));

        $user->update([
            'name' => $cleanName,
            'phone' => $validated['phone'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'تم تحديث بياناتك الشخصية بنجاح.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'role' => $user->role,
                ],
            ]);
        }

        return redirect()->route('profile.show')->with('status', 'تم تحديث بياناتك الشخصية بنجاح.');
    }

    /**
     * Check if the user is currently engaged in an active trip.
     */
    protected function hasActiveTrip(User $user): bool
    {
        if ($user->role === 'rider') {
            // Check for boarded bookings
            $hasBoardedBooking = Booking::query()
                ->where('rider_id', $user->id)
                ->whereIn('status', ['boarded', 'in_progress'])
                ->exists();

            if ($hasBoardedBooking) {
                return true;
            }

            // Check for in-progress special requests
            return SpecialRequest::query()
                ->where('rider_id', $user->id)
                ->where('status', 'accepted')
                ->exists();
        }

        if ($user->role === 'driver') {
            $route = $user->driverRoute;
            if ($route) {
                $hasBoardedPassenger = Booking::query()
                    ->where('route_id', $route->id)
                    ->whereIn('status', ['boarded', 'in_progress'])
                    ->exists();

                if ($hasBoardedPassenger) {
                    return true;
                }
            }

            // Check for active accepted special offer
            return SpecialOffer::query()
                ->where('driver_id', $user->id)
                ->where('status', 'accepted')
                ->whereHas('request', function ($q) {
                    $q->where('status', 'accepted');
                })
                ->exists();
        }

        return false;
    }
}
