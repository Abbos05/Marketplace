<?php

namespace App\Notifications;

use App\Mail\MarketplaceAlertMail;
use App\Models\User;
use App\Services\TransactionalMailService;
use App\Support\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MarketplaceAlert extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public ?string $actionUrl = null,
        public string $category = NotificationCategory::General,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (
            $notifiable instanceof User
            && app(TransactionalMailService::class)->canSendTo($notifiable, $this->category)
        ) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MarketplaceAlertMail
    {
        $url = $this->actionUrl;

        if ($url !== null && ! str_starts_with($url, 'http')) {
            $url = url($url);
        }

        return (new MarketplaceAlertMail($this->title, $this->body, $url))
            ->to(app(TransactionalMailService::class)->recipientFor($notifiable));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title'      => $this->title,
            'body'       => $this->body,
            'action_url' => $this->actionUrl,
            'category'   => $this->category,
        ];
    }
}
