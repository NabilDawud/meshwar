<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class GazetteerController extends Controller
{
    /**
     * Return verified OpenStreetMap landmarks and transit stops for the Gaza Strip.
     */
    public function index(): JsonResponse
    {
        $path = base_path('data/gaza-gazetteer.json');

        if (! File::exists($path)) {
            return response()->json(['error' => 'Gazetteer not found'], 404);
        }

        $data = json_decode(File::get($path), true);

        return response()->json([
            'ok' => true,
            'count' => count($data),
            'landmarks' => $data,
        ]);
    }
}
