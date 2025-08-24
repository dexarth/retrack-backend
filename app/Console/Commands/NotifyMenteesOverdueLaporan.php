<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use Carbon\Carbon;
use App\Notifications\GeneralNotification;

class NotifyMenteesOverdueLaporan extends Command
{
    protected $signature = 'mentees:notify-overdue';
    protected $description = 'Notify mentees who have not submitted laporan in the last 48 hours';

    public function handle()
    {
        $cutoff = now()->subHours(48);

        // latest laporan per mentee (table = laporan, key = mentee_id)
        $latest = DB::table('laporan')
            ->select('mentee_id', DB::raw('MAX(created_at) as last_at'))
            ->groupBy('mentee_id');

        $rows = DB::table('mentees')
            ->leftJoinSub($latest, 'last', fn($j) => $j->on('last.mentee_id', '=', 'mentees.id'))
            ->join('users', 'users.id', '=', 'mentees.user_id')
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last.last_at')           // never submitted
                ->orWhere('last.last_at', '<', $cutoff); // older than 48h
            })
            ->get(['mentees.id as mentee_id', 'users.id as user_id', 'last.last_at']);

        $sent = 0;

        foreach ($rows as $r) {
            // de-dupe: once per day per user (change window if you want)
            $key = "overdue48h:{$r->user_id}:" . now()->format('Y-m-d');
            if (Cache::has($key)) continue;

            $user = User::find($r->user_id);
            if (!$user) continue;

            $lastHuman = $r->last_at ? Carbon::parse($r->last_at)->diffForHumans() : null;

            $payload = [
                'title' => 'Ingatkan Laporan',
                'body'  => $lastHuman
                    ? "Anda belum hantar laporan sejak {$lastHuman}."
                    : "Anda belum pernah hantar laporan. Sila hantar laporan pertama.",
                'url'   => url('/mentee/dashboard'),
                'type'  => 'laporan_overdue_48h',
            ];

            $user->notify(new GeneralNotification($payload));

            Cache::put($key, true, now()->addDay());
            $sent++;
        }

        $this->info("Sent {$sent} overdue notifications.");
        return self::SUCCESS;
    }
}

