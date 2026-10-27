<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\SpecialOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    /**
     * Retrieve chat messages for a specific offer thread.
     */
    public function getMessages(int $offerId): JsonResponse
    {
        $user = Auth::user();

        /** @var SpecialOffer $offer */
        $offer = SpecialOffer::with(['request.rider', 'driver'])->findOrFail($offerId);

        // Security check: only the bidding driver and requesting rider can view the chat
        if ($user->id !== $offer->driver_id && $user->id !== $offer->request->rider_id) {
            return response()->json(['error' => 'غير مصرح لك بالاطلاع على هذه المحادثة'], 403);
        }

        $messages = ChatMessage::where('offer_id', $offer->id)
            ->with('sender:id,name,role')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (ChatMessage $m) => [
                'id' => $m->id,
                'sender_id' => $m->sender_id,
                'sender_name' => $m->sender->name,
                'sender_role' => $m->sender->role,
                'is_me' => $m->sender_id === $user->id,
                'message' => $m->message,
                'created_at' => $m->created_at->toISOString(),
                'time_arabic' => $m->created_at->format('H:i'),
            ]);

        // Privacy rule on phone number
        $isDriver = $user->id === $offer->driver_id;
        $otherPartyPhone = null;
        if ($isDriver) {
            // Driver can see rider's phone ONLY if accepted
            if ($offer->status === 'accepted') {
                $otherPartyPhone = $offer->request->rider->phone;
            }
        } else {
            // Rider can see driver's phone on offers
            $otherPartyPhone = $offer->driver->phone;
        }

        return response()->json([
            'ok' => true,
            'offer_id' => $offer->id,
            'price_ils' => (float) $offer->price_ils,
            'status' => $offer->status,
            'status_arabic' => $offer->status_arabic,
            'other_party' => [
                'name' => $isDriver ? $offer->request->rider->name : $offer->driver->name,
                'phone' => $otherPartyPhone,
                'can_call' => ! empty($otherPartyPhone),
            ],
            'request' => [
                'origin' => $offer->request->origin_name,
                'destination' => $offer->request->destination_name,
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Send a new message in the chat thread.
     * Supports offline outbox mutation with client_mutation_id.
     */
    public function sendMessage(Request $request, int $offerId): JsonResponse
    {
        $user = Auth::user();

        /** @var SpecialOffer $offer */
        $offer = SpecialOffer::with('request')->findOrFail($offerId);

        if ($user->id !== $offer->driver_id && $user->id !== $offer->request->rider_id) {
            return response()->json(['error' => 'غير مصرح لك بالإرسال في هذه المحادثة'], 403);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'client_mutation_id' => ['nullable', 'string', 'max:64'],
        ]);

        $mutationId = $validated['client_mutation_id'] ?? null;

        // Offline idempotency
        if ($mutationId) {
            $existing = ChatMessage::where('sender_id', $user->id)
                ->where('client_mutation_id', $mutationId)
                ->with('sender:id,name,role')
                ->first();

            if ($existing) {
                return response()->json([
                    'ok' => true,
                    'already_synced' => true,
                    'message' => [
                        'id' => $existing->id,
                        'sender_id' => $existing->sender_id,
                        'sender_name' => $existing->sender->name,
                        'is_me' => true,
                        'message' => $existing->message,
                        'created_at' => $existing->created_at->toISOString(),
                        'time_arabic' => $existing->created_at->format('H:i'),
                    ],
                ]);
            }
        }

        $chatMessage = ChatMessage::create([
            'offer_id' => $offer->id,
            'sender_id' => $user->id,
            'message' => $validated['message'],
            'client_mutation_id' => $mutationId,
        ]);

        $chatMessage->load('sender:id,name,role');

        return response()->json([
            'ok' => true,
            'message' => [
                'id' => $chatMessage->id,
                'sender_id' => $chatMessage->sender_id,
                'sender_name' => $chatMessage->sender->name,
                'is_me' => true,
                'message' => $chatMessage->message,
                'created_at' => $chatMessage->created_at->toISOString(),
                'time_arabic' => $chatMessage->created_at->format('H:i'),
            ],
        ], 201);
    }
}
