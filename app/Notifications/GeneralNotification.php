<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class GeneralNotification extends Notification implements ShouldBroadcastNow
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

    public function toBroadcast($notifiable): BroadcastMessage
    {
        \Log::info('Broadcast Payload', ['payload' => $this->payload]);
        return new BroadcastMessage($this->payload);
    }

    // no $notifiable parameter here
    public function broadcastOn(): PrivateChannel
    {
        // if you want the default “private-App.Models.User.{id}” you can
        // simply return that—Laravel will already know the notifiable,
        // but if you really need its ID, you can grab it off $this->notifiable
        return new PrivateChannel('App.Models.User.' . $this->notifiable->id);
    }

    public function broadcastAs(): string
    {
        return 'general-notification';
    }
}
