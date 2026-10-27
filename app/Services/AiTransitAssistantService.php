<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class AiTransitAssistantService
{
    /**
     * Generate optional transit advice via Anthropic Claude API if key is configured.
     * Fully decoupled: returns deterministic advice or null when key is absent.
     *
     * @param  array<string, mixed>  $itinerary  Top proposed route itinerary
     * @param  string  $from  Origin point
     * @param  string  $to  Destination point
     * @return array<string, mixed>|null
     */
    public function generateAdvice(array $itinerary, string $from, string $to): ?array
    {
        $apiKey = env('ANTHROPIC_API_KEY') ?: config('services.anthropic.key');

        // Fully usable without AI key: returns zero-cost deterministic advice
        if (empty($apiKey)) {
            return [
                'enabled' => false,
                'provider' => 'deterministic_fallback',
                'tip' => $this->getDeterministicTip($itinerary, $from, $to),
            ];
        }

        try {
            $isTransfer = ($itinerary['transfers'] ?? 0) > 0;
            $transferJunction = $itinerary['transfer_junction'] ?? '';
            $vehicleType = $itinerary['vehicle']['type_arabic'] ?? 'المركبة';

            $prompt = "أنت مساعد السفر الذكي لتطبيق مشوار في قطاع غزة. المسافر ينطلق من '{$from}' إلى '{$to}'. ";
            if ($isTransfer) {
                $prompt .= "الرحلة تتطلب تبديل مركبة عند '{$transferJunction}'. ";
            } else {
                $prompt .= "الرحلة مباشرة عبر {$vehicleType}. ";
            }
            $prompt .= 'اكتب نصيحة سريعة ومفيدة جداً للمسافر في جملة واحدة باللغة العربية (مثل التواجد مبكراً، أو مراعاة وقت التبديل). لا تزد عن 20 كلمة.';

            $response = Http::timeout(3)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-3-5-haiku-20241022',
                    'max_tokens' => 100,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['content'][0]['text'] ?? null;
                if ($text) {
                    return [
                        'enabled' => true,
                        'provider' => 'anthropic_claude',
                        'tip' => trim($text),
                    ];
                }
            }
        } catch (Throwable $e) {
            // Graceful fallback on any network error or timeout
        }

        return [
            'enabled' => false,
            'provider' => 'deterministic_fallback',
            'tip' => $this->getDeterministicTip($itinerary, $from, $to),
        ];
    }

    /**
     * Provide helpful deterministic advice when AI key is not set or network is offline.
     */
    protected function getDeterministicTip(array $itinerary, string $from, string $to): string
    {
        if (($itinerary['transfers'] ?? 0) > 0) {
            $junction = $itinerary['transfer_junction'] ?? 'نقطة التبديل';
            $wait = $itinerary['transfer_wait_minutes'] ?? 15;

            return "نصيحة مشوار: الرحلة تتطلب تبديل مركبة عند {$junction} (متوقع انتظار حوالي {$wait} دقيقة)، يفضل تجهيز الأجرة نقداً مسبقاً.";
        }

        $time = $itinerary['departure_time'] ?? 'الموعد المحدد';

        return "نصيحة مشوار: رحلة مباشرة على الخط، يفضل التواجد عند نقطة الركوب قبل موعد أول رحلة ({$time}) بـ 5 إلى 10 دقائق.";
    }
}
