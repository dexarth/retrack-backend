<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Mentor;

class ReportController extends Controller
{
    public function laporanMenteePdf(Request $req)
    {
        // Optional: pass filters to jsreport so your script can build WHERE clause
        $filters = [
            'mentee_id' => $req->input('mentee_id'),
            'mentor_id' => $req->input('mentor_id'),
            'status'    => $req->input('status'),
            'from'      => $req->input('from'),
            'to'        => $req->input('to'),
        ];

        $payload = [
            'template' => [
                'name' => config('services.jsreport.template'),
                // or 'shortid' => 'xxxxxxxx'
            ],
            'data' => [
                // Your jsreport script *can* ignore this if it queries MySQL itself.
                // But we pass filters so the script can narrow results.
                'filters'    => $filters,
                'printedBy'  => $req->user()->name ?? 'MENTOR',
            ],
            // Optional: chrome-pdf options, timeout, etc.
            'options' => [
                'timeout' => 60000
            ]
        ];

        $res = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])
            ->timeout(120)
            ->post(rtrim(config('services.jsreport.url'), '/') . '/api/report', $payload);

        if (!$res->ok()) {
            return response()->json(['error' => 'Failed to render report', 'detail' => $res->body()], 502);
        }

        $filename = 'senarai-laporan-' . now()->format('Ymd_His') . '.pdf';
        return response($res->body(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function staffMonitorings(Request $req)
    {
        $user = $req->user();
        $role = strtolower($user->role ?? '');

        // incoming filters
        $filters = [
            // Frontend will send a single day (required by your UI); still optional here.
            'date'       => $req->input('date'),          // "YYYY-MM-DD"
            'kategori'   => $req->input('kategori'),      // "individu" | "kelompok"
            'csi_id'     => $req->input('csi_id'),
            'id_prospek' => $req->input('id_prospek'),
            'search'     => $req->input('search'),
            // mentor_id can be number or array; will be sanitized below
            'mentor_id'  => $req->input('mentor_id'),
            // for header
            'daerah'     => $req->input('daerah'),
        ];

        // --- role-based scoping ---
        if ($role === 'mentor') {
            // lock to own mentor_id (single)
            $filters['mentor_id'] = (int) $user->id;
            $filters['daerah']    = $filters['daerah'] ?: ($user->parol_daerah ?? '');
        } elseif ($role === 'admin') {
            // limit to mentors under admin's daerah
            $adminDaerah = $user->parol_daerah ?? null;
            $allowedMentorIds = Mentor::where('parol_daerah', $adminDaerah)->pluck('id')->map(fn($v)=>(int)$v)->all();

            // requested mentor_id may be number or array; intersect with allowed
            $requested = $req->input('mentor_id');
            $requestedIds = is_array($requested)
                ? array_values(array_filter(array_map('intval', $requested)))
                : (isset($requested) ? [ (int) $requested ] : []);

            $useIds = $requestedIds ? array_values(array_intersect($requestedIds, $allowedMentorIds)) : $allowedMentorIds;

            // pass array; jsreport script accepts single/array transparently
            $filters['mentor_id'] = $useIds;
            $filters['daerah']    = $filters['daerah'] ?: $adminDaerah;
        } else {
            // superadmin/others – leave as provided
        }

        $payload = [
            'template' => [
                'name' => config('services.jsreport.template_pemantauan'),
            ],
            'data' => [
                'filters'   => $filters,
                'printedBy' => $user->name ?? 'SYSTEM',
            ],
            'options' => [
                'timeout' => 60000,
            ],
        ];

        $res = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(120)
            ->post(rtrim(config('services.jsreport.url'), '/') . '/api/report', $payload);

        if (!$res->ok()) {
            return response()->json(['error' => 'Failed to render report', 'detail' => $res->body()], 502);
        }

        $filename = 'laporan-pemantauan-' . now()->format('Ymd_His') . '.pdf';
        return response($res->body(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }
}
