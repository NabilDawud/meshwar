<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RoleChoiceController extends Controller
{
    /**
     * Show role choice view if user has no role assigned.
     */
    public function show(): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user && ! empty($user->role)) {
            return $user->role === 'driver'
                ? redirect()->route('driver.dashboard')
                : redirect()->route('rider.home');
        }

        return view('auth.role-choice');
    }

    /**
     * Persist selected role and redirect to designated home.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:rider,driver'],
            'phone' => ['required', 'string', 'regex:/^(\+?(970|972)|0)?5[69]\d{7}$/'],
        ], [
            'role.required' => 'يرجى اختيار نوع الحساب (سائق أو راكب).',
            'phone.required' => 'رقم الجوال مطلوب للتواصل وتأكيد الرحلات.',
            'phone.regex' => 'يرجى إدخال رقم جوال فلسطيني صحيح (مثال: 0599123456 أو 0569123456).',
        ]);

        /** @var User $user */
        $user = Auth::user();
        $user->update([
            'role' => $validated['role'],
            'phone' => $validated['phone'],
        ]);

        return $validated['role'] === 'driver'
            ? redirect()->route('driver.dashboard')
            : redirect()->route('rider.home');
    }
}
