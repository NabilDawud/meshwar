<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MishwarNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  string  $title  Arabic title (e.g. 'حجز مقعد جديد')
     * @param  string  $message  Arabic message details
     * @param  string  $type  Notification category: 'booking', 'location', 'offer', 'chat', 'rating', 'general'
     * @param  string  $icon  Emoji icon (e.g. '🚐', '📍', '🎉', '💬', '⭐')
     * @param  string|null  $actionUrl  Target URL for navigation
     * @param  array  $extra  Additional payload metadata
     */
    public function __construct(
        public string $title,
        public string $message,
        public string $type = 'general',
        public string $icon = '🔔',
        public ?string $actionUrl = null,
        public array $extra = []
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'icon' => $this->icon,
            'action_url' => $this->actionUrl,
            'extra' => $this->extra,
            'created_at' => now()->toISOString(),
        ];
    }
}
