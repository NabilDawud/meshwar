<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class AiTransitAssistantService
{
    /**
     * Generate optional transit advice via Google Gemini API (free tier) or Anthropic Claude API.
     * Fully decoupled: returns deterministic advice when keys are absent or on network errors.
     *
     * @param  array<string, mixed>  $itinerary  Top proposed route itinerary
     * @param  string  $from  Origin point
     * @param  string  $to  Destination point
     * @return array<string, mixed>|null
     */
    public function generateAdvice(array $itinerary, string $from, string $to): ?array
    {
        $geminiKey = config('services.gemini.key');
        $anthropicKey = config('services.anthropic.key');

        $prompt = $this->buildPrompt($itinerary, $from, $to);

        // 1. Google Gemini (free tier default)
        if (! empty($geminiKey)) {
            $geminiAdvice = $this->queryGemini($geminiKey, $prompt);
            if ($geminiAdvice) {
                return $geminiAdvice;
            }
        }

        // 2. Anthropic Claude (if configured)
        if (! empty($anthropicKey)) {
            $anthropicAdvice = $this->queryAnthropic($anthropicKey, $prompt);
            if ($anthropicAdvice) {
                return $anthropicAdvice;
            }
        }

        // 3. Deterministic zero-cost fallback
        return [
            'enabled' => false,
            'provider' => 'deterministic_fallback',
            'tip' => $this->getDeterministicTip($itinerary, $from, $to),
        ];
    }

    /**
     * Call Google Gemini API (free tier model: gemini-3.5-flash-lite).
     *
     * @return array<string, mixed>|null
     */
    protected function queryGemini(string $apiKey, string $prompt): ?array
    {
        try {
            $model = config('services.gemini.model', 'gemini-3.5-flash-lite');

            $response = Http::timeout(6)
                ->connectTimeout(4)
                ->withOptions([
                    'force_ip_resolve' => 'v4',
                ])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Expect' => '',
                    'x-goog-api-key' => $apiKey,
                ])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'maxOutputTokens' => 100,
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($text) {
                    return [
                        'enabled' => true,
                        'provider' => 'google_gemini',
                        'tip' => trim($text),
                    ];
                }
            }
        } catch (Throwable $e) {
            // Graceful fallback on network timeout or DNS failure
        }

        return null;
    }

    /**
     * Call Anthropic Claude API.
     *
     * @return array<string, mixed>|null
     */
    protected function queryAnthropic(string $apiKey, string $prompt): ?array
    {
        try {
            $response = Http::timeout(4)
                ->withOptions([
                    'force_ip_resolve' => 'v4',
                ])
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                    'Expect' => '',
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
            // Graceful fallback
        }

        return null;
    }

    /**
     * Build the context prompt for the transit advisor.
     */
    protected function buildPrompt(array $itinerary, string $from, string $to): string
    {
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

        return $prompt;
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
