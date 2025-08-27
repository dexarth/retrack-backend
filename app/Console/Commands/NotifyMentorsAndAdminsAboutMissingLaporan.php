<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Notifications\GeneralNotification;

class NotifyMentorsAndAdminsAboutMissingLaporan extends Command
{
    protected $signature = 'laporan:notify-missing';
    protected $description = 'Notify mentors and admins about mentees who did not submit laporan today';

    public function handle()
    {
        $today = now()->startOfDay();

        // ✅ FIXED: laporan.mentee_id == mentees.user_id
        $submittedToday = DB::table('laporan')
            ->whereDate('created_at', $today->toDateString())
            ->pluck('mentee_id');

        // ✅ FIXED: compare against mentees.user_id, not mentees.id
        $missing = DB::table('mentees as m')
            ->whereNotIn('m.user_id', $submittedToday)
            ->leftJoin('mentors as mr', 'mr.user_id', '=', 'm.mentor_id')    // mentors.user_id == mentees.mentor_id
            ->leftJoin('users as mu', 'mu.id', '=', 'm.mentor_id')           // mentor user (to get name)
            ->whereNotNull('m.mentor_id')                                    // only mentees with mentor
            ->select([
                'm.id as mentee_id',
                'm.mentor_id',
                'mr.parol_daerah as mentor_daerah',
                'mu.name as mentor_name',
            ])
            ->get();

        if ($missing->isEmpty()) {
            $this->info('✅ Semua mentee telah hantar laporan hari ini.');
            return self::SUCCESS;
        }

        // ---------------------- MENTOR NOTIFY ----------------------
        $byMentor = $missing->groupBy('mentor_id');
        foreach ($byMentor as $mentorId => $rows) {
            $mentor = User::find($mentorId);
            if (!$mentor) continue;

            $cacheKey = "laporan:notify:mentor:$mentorId:" . $today->toDateString();
            if (Cache::has($cacheKey)) continue;

            $mentor->notify(new GeneralNotification([
                'title'   => 'Laporan Mentee Belum Dihantar',
                'message' => "Terdapat {$rows->count()} mentee belum hantar laporan hari ini.",
                'url'     => '/mentor/mentees-lambat-hantar',
                'type'    => 'laporan_missing_mentor',
            ]));

            Cache::put($cacheKey, true, now()->addDay());
            $this->info("✅ Notified mentor ID {$mentorId} for {$rows->count()} mentees.");
        }

        // ---------------------- ADMIN NOTIFY ----------------------
        $mentorBreakdown = $byMentor->map(function ($rows, $mentorId) {
            $first = $rows->first();
            return [
                'mentor_id'   => $mentorId,
                'mentor_name' => $first->mentor_name ?: "Mentor {$mentorId}",
                'daerah'      => $first->mentor_daerah,
                'count'       => $rows->count(),
            ];
        });

        $byDaerah = $mentorBreakdown->groupBy('daerah');
        foreach ($byDaerah as $daerah => $items) {
            if (!$daerah) continue;

            $admins = DB::table('admins')
                ->join('users', 'users.id', '=', 'admins.user_id')
                ->where('admins.parol_daerah', $daerah)
                ->pluck('users.id');

            if ($admins->isEmpty()) continue;

            $total = $items->sum('count');
            if ($total <= 0) continue;

            $perMentorText = $items
                ->map(fn($i) => "{$i['mentor_name']} ({$i['count']})")
                ->implode(', ');

            foreach ($admins as $adminId) {
                $admin = User::find($adminId);
                if (!$admin) continue;

                $cacheKey = "laporan:notify:admin:$adminId:" . $today->toDateString();
                if (Cache::has($cacheKey)) continue;

                $admin->notify(new GeneralNotification([
                    'title'   => 'Laporan Mentee Belum Dihantar',
                    'message' => "Terdapat {$total} mentee belum hantar laporan hari ini.",
                    'url'     => '/admin/mentees-lambat-hantar',
                    'type'    => 'laporan_missing_admin',
                ]));

                Cache::put($cacheKey, true, now()->addDay());
                $this->info("✅ Notified admin ID {$adminId} for daerah {$daerah} (total {$total}).");
            }
        }

        return self::SUCCESS;
    }
}
