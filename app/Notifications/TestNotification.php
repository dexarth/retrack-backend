<?php

// app/Notifications/TestNotification.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class TestNotification extends Notification
{
    use Queueable;

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title'   => 'Notifikasi Ujian',
            'message' => 'Ini adalah mesej ujian dari sistem.',
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'title'   => 'Notifikasi Ujian',
            'message' => 'Ini adalah mesej ujian dari sistem.',
        ]);
    }
}

