<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\PrivateChannel;

class GeneralNotification extends Notification
{
    use Queueable;

    protected array $payload;
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable): array
    {
        return $this->payload;
    }

    public function toBroadcast($notifiable)
    {
        // broadcast the same shape
        return new BroadcastMessage($this->payload);
    }
}
