<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    // GET /api/notifications
    public function index(Request $request)
    {
        $per  = (int) $request->query('per_page', 10);
        $page = (int) $request->query('page', 1);

        $paginator = $request->user()
            ->notifications()
            ->latest()
            ->simplePaginate($per, ['*'], 'page', $page);

        $items = collect($paginator->items())
            ->map(fn (DatabaseNotification $n) => $this->mapNotif($n))
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

    private function mapNotif(DatabaseNotification $n): array
    {
        $d = is_array($n->data) ? $n->data : (array) $n->data;

        return [
            'id'         => $n->id,
            'title'      => $d['title'] ?? 'Notifikasi',
            'body'       => $d['body'] ?? ($d['message'] ?? ''),
            'actor_name' => $d['actor_name'] ?? null,
            'url'        => $d['url'] ?? null,
            'created_at' => optional($n->created_at)->toISOString(),
            'read_at'    => optional($n->read_at)->toISOString(),
        ];
    }

    /**
     * Config-driven trigger.
     * notifications_config example:
     *  table_name: 'sudut_infos'
     *  sender: 'auth'
     *  receiver: 'all' | 'admin' | 'auth' | 'role:mentor' | 'roles:admin,mentor,mentee' | 'mentor_id'
     *  payload_template JSON: {"url":":prefix/sudut-info/:slug","title":"Sudut Info Baharu","body":":user menerbitkan ':title'."}
     */
    public static function trigger(string $tableName, $primary): void
    {
        try {
            Log::info("🔔 Trigger notification start", [
                'table'  => $tableName,
                'record' => $primary,
            ]);

            $config = DB::table('notifications_config')
                ->where('table_name', $tableName)
                ->first();

            if (!$config) {
                Log::warning("⚠️ No notification config found", ['table' => $tableName]);
                return;
            }

            // ----- Resolve sender -----
            $sender = null;
            if ($config->sender === 'auth') {
                $sender = auth()->user();
            } elseif (!empty($primary->{$config->sender})) {
                $sender = User::find($primary->{$config->sender});
            }

            // ----- Placeholders (except :prefix which is per-recipient) -----
            $placeholders = [
                ':id'     => $primary->id          ?? null,
                ':uuid'   => $primary->uuid        ?? null,
                ':slug'   => $primary->slug        ?? ($primary->uuid ?? ($primary->id ?? '')),
                ':title'  => $primary->title       ?? ($primary->name ?? null),
                ':user'   => $sender?->name        ?? 'Sistem',
                ':sender' => $sender?->name        ?? 'Sistem',
            ];

            // Base payload without :prefix
            $template = json_decode($config->payload_template, true) ?? [];
            $basePayload = [];
            foreach ($template as $k => $v) {
                $basePayload[$k] = is_string($v) ? strtr($v, $placeholders) : $v;
            }

            // Build per-user payload (inject :prefix, :role)
            $payloadFor = function (User $u) use ($basePayload, $placeholders): array {
                // Map role → route prefix (adjust if your paths differ)
                $prefixMap = [
                    'superadmin' => '/admin',
                    'admin'      => '/admin',
                    'mentor'     => '/mentor',
                    'mentee'     => '/mentee',
                ];
                $prefix = $prefixMap[$u->role] ?? '';

                $p = $basePayload;
                if (!empty($p['url'])) {
                    $p['url'] = strtr($p['url'], [
                        ':prefix' => $prefix,
                        ':role'   => $u->role,
                        ':slug'   => $placeholders[':slug'],
                        ':id'     => $placeholders[':id'],
                    ]);
                } else {
                    // Fallback if url missing in template
                    $p['url'] = "{$prefix}/sudut-info/{$placeholders[':slug']}";
                }
                return $p;
            };

            Log::info("📦 Notification payload prepared", [
                'sender'          => $sender?->only(['id','name','role']),
                'receiver_config' => $config->receiver,
            ]);

            // ----- Resolve receiver(s) & notify -----
            if ($config->receiver === 'auth') {
                $receiver = auth()->user();
                if ($receiver) {
                    $receiver->notify(new GeneralNotification($payloadFor($receiver)));
                    Log::info("✅ Notified auth user", ['id' => $receiver->id, 'name' => $receiver->name]);
                }

            } elseif (str_starts_with($config->receiver, 'role:')) {
                $role = substr($config->receiver, 5);
                User::where('role', $role)
                    ->select(['id','role'])
                    ->chunkById(500, function ($chunk) use ($payloadFor) {
                        foreach ($chunk as $u) {
                            $u->notify(new GeneralNotification($payloadFor($u)));
                        }
                    });
                Log::info("✅ Notified role users", ['role' => $role]);

            } elseif (str_starts_with($config->receiver, 'roles:')) {
                $roles = array_filter(array_map('trim', explode(',', substr($config->receiver, 6))));
                if ($roles) {
                    User::whereIn('role', $roles)
                        ->select(['id','role'])
                        ->chunkById(500, function ($chunk) use ($payloadFor) {
                            foreach ($chunk as $u) {
                                $u->notify(new GeneralNotification($payloadFor($u)));
                            }
                        });
                    Log::info("✅ Notified roles", ['roles' => $roles]);
                }

            } elseif ($config->receiver === 'all') {
                // Optional guard: only broadcast if creator is admin
                if (($sender?->role ?? null) !== 'admin') {
                    Log::info("🔕 Broadcast skipped (sender not admin).", ['sender_role' => $sender?->role]);
                } else {
                    User::query()
                        ->when($sender, fn($q) => $q->where('id', '!=', $sender->id)) // exclude sender if you want
                        ->select(['id','role'])
                        ->chunkById(500, function ($chunk) use ($payloadFor) {
                            foreach ($chunk as $u) {
                                $u->notify(new GeneralNotification($payloadFor($u)));
                            }
                        });
                    Log::info("✅ Broadcasted to all users (except sender).");
                }

            } elseif ($config->receiver === 'admin') {
                // parol_daerah-based admin resolution
                $daerah = $primary->parol_daerah ?? null;
                if (!$daerah && !empty($primary->mentor_id)) {
                    $daerah = DB::table('mentors')
                        ->where(function ($q) use ($primary) {
                            $q->where('user_id', $primary->mentor_id)
                              ->orWhere('id', $primary->mentor_id);
                        })
                        ->value('parol_daerah');
                }

                $adminIds = DB::table('admins')
                    ->when($daerah, fn($q) => $q->where('parol_daerah', $daerah))
                    ->pluck('user_id');

                $targets = User::whereIn('id', $adminIds)->select(['id','role'])->get();
                foreach ($targets as $u) {
                    $u->notify(new GeneralNotification($payloadFor($u)));
                }

                Log::info("✅ Notified admin(s)", [
                    'daerah' => $daerah,
                    'count'  => $targets->count(),
                    'ids'    => $targets->pluck('id'),
                ]);

            } elseif (!empty($primary->{$config->receiver})) {
                // direct user id column on primary (e.g., mentor_id, mentee_id)
                $receiver = User::find($primary->{$config->receiver});
                if ($receiver) {
                    $receiver->notify(new GeneralNotification($payloadFor($receiver)));
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
                'error'     => $e->getMessage(),
                'table'     => $tableName,
                'record_id' => $primary->id ?? null,
                'user_id'   => auth()->id(),
                'trace'     => $e->getTraceAsString(),
            ]);
        }
    }

    // PATCH /api/notifications/{id}/read
    public function markAsRead(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $notification = $user->notifications()->where('id', $id)->first();

            if (!$notification) {
                return response()->json(['message' => 'Not found'], 404);
            }

            $notification->markAsRead();
            return response()->json(['success' => true]);

        } catch (\Throwable $e) {
            Log::error('❌ Error marking notification as read', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Internal server error'], 500);
        }
    }
}
