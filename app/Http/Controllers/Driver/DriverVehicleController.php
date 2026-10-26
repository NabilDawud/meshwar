<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DriverVehicleController extends Controller
{
    /**
     * Get the authenticated driver's vehicle.
     */
    public function show(): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'driver') {
            return response()->json(['error' => 'حساب السائق مطلوب'], 403);
        }

        $vehicle = $user->vehicle;

        return response()->json([
            'ok' => true,
            'vehicle' => $vehicle,
            'has_vehicle' => $vehicle !== null,
        ]);
    }

    /**
     * Store or update the authenticated driver's single vehicle.
     * Enforces the 1:1 driver to vehicle rule.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'driver') {
            return response()->json(['error' => 'حساب السائق مطلوب'], 403);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:car,bus,ajala'],
            'seat_count' => ['required', 'integer', 'min:1', 'max:60'],
            'license_plate' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:active,maintenance'],
            'photos' => ['nullable', 'array', 'max:4'],
            'photos.*' => ['nullable', 'string'],
        ], [
            'type.required' => 'نوع المركبة مطلوب (عجلة أو سيارة أو باص).',
            'type.in' => 'نوع المركبة غير صالح.',
            'seat_count.required' => 'عدد المقاعد مطلوب.',
            'seat_count.min' => 'عدد المقاعد يجب أن يكون مقعداً واحداً على الأقل.',
            'seat_count.max' => 'عدد المقاعد لا يتجاوز 60 مقعداً.',
            'photos.max' => 'الحد الأقصى للصور هو 4 صور.',
        ]);

        $photos = $validated['photos'] ?? [];

        // Handle uploaded image files if any
        if ($request->hasFile('photo_files')) {
            $uploadedFiles = $request->file('photo_files');
            if (is_array($uploadedFiles)) {
                foreach (array_slice($uploadedFiles, 0, 4) as $file) {
                    $filename = 'vehicle_'.$user->id.'_'.Str::random(8).'.'.$file->getClientOriginalExtension();
                    $path = $file->storeAs('public/vehicles', $filename);
                    $photos[] = Storage::url($path);
                }
            }
        }

        // Limit to 4 photos maximum
        $photos = array_slice($photos, 0, 4);

        $vehicle = Vehicle::updateOrCreate(
            ['driver_id' => $user->id],
            [
                'type' => $validated['type'],
                'seat_count' => $validated['seat_count'],
                'license_plate' => $validated['license_plate'] ?? null,
                'status' => $validated['status'] ?? 'active',
                'photos' => $photos,
            ]
        );

        return response()->json([
            'ok' => true,
            'message' => 'تم حفظ بيانات المركبة بنجاح',
            'vehicle' => $vehicle,
        ]);
    }
}
