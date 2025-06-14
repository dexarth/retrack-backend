<?php

namespace App\Http\Controllers;

use App\Notifications\GeneralNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public static function trigger(string $tableName, $primary): void
    {
        try {
            $config = DB::table('notifications_config')->where('table_name', $tableName)->first();
            if (!$config) return;

            // Resolve sender
            $sender = null;
            if ($config->sender === 'auth') {
                $sender = auth()->user();
            } elseif (!empty($primary->{$config->sender})) {
                $sender = \App\Models\User::find($primary->{$config->sender});
            }

            // Build payload with sender info
            $placeholders = [
                ':id'     => $primary->id,
                ':sender' => $sender?->name ?? 'Sistem',
            ];
            $template = json_decode($config->payload_template, true);
            $payload = array_map(fn($val) => strtr($val, $placeholders), $template);

            // Resolve receiver
            if ($config->receiver === 'auth') {
                $receiver = auth()->user();
                $receiver?->notify(new GeneralNotification($payload));
            } elseif (str_starts_with($config->receiver, 'role:')) {
                $role = str_replace('role:', '', $config->receiver);
                $users = \App\Models\User::where('role', $role)->get();
                foreach ($users as $user) {
                    $user->notify(new GeneralNotification($payload));
                }
            } elseif (!empty($primary->{$config->receiver})) {
                $receiver = \App\Models\User::find($primary->{$config->receiver});
                $receiver?->notify(new GeneralNotification($payload));
            }

        } catch (\Throwable $e) {
            \Log::error("Notification failed", [
                'error' => $e->getMessage(),
                'table' => $tableName,
                'record_id' => $primary->id ?? null,
                'user_id' => auth()->id(),
            ]);
        }
    }
}
