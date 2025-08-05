<?php

namespace App\Http\Controllers;

use App\Notifications\GeneralNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public static function trigger(string $tableName, $primary): void
    {
        try {
            Log::info("data notification:", ['tableName' => $tableName, 'primary' => $primary]);
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
                ':user'   => $sender?->name ?? 'Sistem',
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
                // mentor_id actually holds the users.id, so find the User directly
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

    public function markAsRead(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $notification = $user->notifications()->where('id', $id)->first();

            if (!$notification) {
                return response()->json(['message' => 'Not found'], 404);
            }

            $notification->markAsRead(); // updates read_at column

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            \Log::error('❌ Error marking notification as read', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Internal server error'], 500);
        }
    }
}
