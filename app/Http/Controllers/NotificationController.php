<?php

namespace App\Http\Controllers;

use App\Notifications\GeneralNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $per  = (int) $request->query('per_page', 10);
        $page = (int) $request->query('page', 1);

        $paginator = $request->user()
            ->notifications()
            ->latest()
            ->simplePaginate($per, ['*'], 'page', $page);

        $items = collect($paginator->items())
            ->map(fn (\Illuminate\Notifications\DatabaseNotification $n) => $this->mapNotif($n))
            ->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $per,
                'has_more'     => $paginator->hasMorePages(),
            ],
        ]);
    }

    private function mapNotif(\Illuminate\Notifications\DatabaseNotification $n): array
    {
        $d = is_array($n->data) ? $n->data : (array) $n->data;

        return [
            'id'         => $n->id,
            'title'      => $d['title'] ?? 'Notifikasi',
            'body'       => $d['body'] ?? ($d['message'] ?? ''),
            'actor_name' => $d['actor_name'] ?? null,
            'url'       => $d['url'] ?? null,
            'created_at' => optional($n->created_at)->toISOString(),
            'read_at'    => optional($n->read_at)->toISOString(),
        ];
    }

    public static function trigger(string $tableName, $primary): void
    {
        try {
            Log::info("🔔 Trigger notification start", [
                'table' => $tableName,
                'record' => $primary,
            ]);

            $config = DB::table('notifications_config')
                ->where('table_name', $tableName)
                ->first();

            if (!$config) {
                Log::warning("⚠️ No notification config found", ['table' => $tableName]);
                return;
            }

            // Resolve sender
            $sender = null;
            if ($config->sender === 'auth') {
                $sender = auth()->user();
            } elseif (!empty($primary->{$config->sender})) {
                $sender = \App\Models\User::find($primary->{$config->sender});
            }

            // Build payload
            $placeholders = [
                ':id'     => $primary->id ?? null,
                ':uuid'   => $primary->uuid ?? null,
                ':user'   => $sender?->name ?? 'Sistem',
                ':sender' => $sender?->name ?? 'Sistem',
            ];
            $template = json_decode($config->payload_template, true) ?? [];
            $payload  = array_map(fn($val) => strtr($val, $placeholders), $template);

            Log::info("📦 Notification payload prepared", [
                'payload' => $payload,
                'sender'  => $sender?->only(['id','name','role']),
                'receiver_config' => $config->receiver,
            ]);

            // Resolve receiver(s)
            if ($config->receiver === 'auth') {
                $receiver = auth()->user();
                if ($receiver) {
                    $receiver->notify(new GeneralNotification($payload));
                    Log::info("✅ Notified auth user", ['id' => $receiver->id, 'name' => $receiver->name]);
                }
            } elseif (str_starts_with($config->receiver, 'role:')) {
                $role = str_replace('role:', '', $config->receiver);
                $users = \App\Models\User::where('role', $role)->get();
                foreach ($users as $user) {
                    $user->notify(new GeneralNotification($payload));
                }
                Log::info("✅ Notified role users", ['role' => $role, 'count' => $users->count()]);
            } elseif ($config->receiver === 'admin') {
                // parol_daerah based admin resolution
                $daerah = $primary->parol_daerah ?? null;

                if (!$daerah && !empty($primary->mentor_id)) {
                    $daerah = \DB::table('mentors')
                        ->where(function ($q) use ($primary) {
                            $q->where('user_id', $primary->mentor_id)
                            ->orWhere('id', $primary->mentor_id);
                        })
                        ->value('parol_daerah');
                }

                $adminIds = \DB::table('admins')
                    ->when($daerah, fn($q) => $q->where('parol_daerah', $daerah))
                    ->pluck('user_id');

                $targets = \App\Models\User::whereIn('id', $adminIds)->get();
                foreach ($targets as $u) {
                    $u->notify(new \App\Notifications\GeneralNotification($payload));
                }
                Log::info("✅ Notified admin(s)", [
                    'daerah' => $daerah,
                    'count'  => $targets->count(),
                    'ids'    => $targets->pluck('id'),
                ]);
            } elseif (!empty($primary->{$config->receiver})) {
                $receiver = \App\Models\User::find($primary->{$config->receiver});
                if ($receiver) {
                    $receiver->notify(new GeneralNotification($payload));
                    Log::info("✅ Notified direct receiver", ['id' => $receiver->id, 'name' => $receiver->name]);
                }
            } else {
                Log::warning("⚠️ No receiver resolved", [
                    'config_receiver' => $config->receiver,
                    'primary'         => $primary,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("❌ Notification failed", [
                'error' => $e->getMessage(),
                'table' => $tableName,
                'record_id' => $primary->id ?? null,
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString(),
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
