<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class GeoController extends Controller
{
    /** Build a Radar HTTP client (skip SSL verify only in local) */
    private function radar()
    {
        return Http::withOptions([
            'verify' => !app()->isLocal(),               // dev-only skip
        ])->withHeaders([
            'Authorization' => config('services.radar.secret'),
            'Accept'        => 'application/json',
        ]);
    }

    /** Pull useful components from Radar response */
    private function extractComponents(?array $addr): ?array
    {
        if (!$addr) return null;
        return [
            'layer'        => $addr['layer']        ?? null,
            'number'       => $addr['number']       ?? null,
            'street'       => $addr['street']       ?? null,
            'neighborhood' => $addr['neighborhood'] ?? null,
            'city'         => $addr['city']         ?? null,
            'state'        => $addr['state']        ?? null,
            'postalCode'   => $addr['postalCode']   ?? null,
            'country'      => $addr['country']      ?? null,
            'countryCode'  => $addr['countryCode']  ?? null,
            'confidence'   => $addr['confidence']   ?? null,
            'latitude'     => $addr['latitude']     ?? null,
            'longitude'    => $addr['longitude']    ?? null,
            'formatted'    => $addr['formattedAddress'] ?? null,
        ];
    }

    public function reverse(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $lat = $request->query('lat');
        $lng = $request->query('lng');

        $cacheKey = "radar_rev_{$lat}_{$lng}";

        try {
            $result = Cache::remember($cacheKey, 15 * 60, function () use ($lat, $lng) {
                $resp = $this->radar()->get('https://api.radar.io/v1/geocode/reverse', [
                    'coordinates' => "{$lat},{$lng}",
                    'limit'       => 1,
                ]);

                if ($resp->failed()) {
                    return ['error' => true, 'status' => $resp->status(), 'body' => $resp->json()];
                }
                return ['error' => false, 'status' => 200, 'body' => $resp->json()];
            });
        } catch (\Throwable $e) {
            // Transport (e.g., cURL 60) → return clean 502
            return response()->json(['message' => 'Radar transport error', 'error' => $e->getMessage()], 502);
        }

        if ($result['error']) {
            return response()->json(['message' => 'Radar API error', 'body' => $result['body']], $result['status']);
        }

        $addr = $result['body']['addresses'][0] ?? null;
        return response()->json([
            'formattedAddress' => $addr['formattedAddress'] ?? null,
            'components'       => $this->extractComponents($addr),
            'raw'              => $result['body'],
        ]);
    }

    public function forward(Request $request)
    {
        $request->validate([
            'q'       => 'required|string|min:2',
            'limit'   => 'nullable|integer|min:1|max:5',
            'country' => 'nullable|string|size:2',
            'lang'    => 'nullable|string|size:2',
            'lat'     => 'nullable|numeric|between:-90,90',
            'lng'     => 'nullable|numeric|between:-180,180',
        ]);

        $q       = $request->query('q');
        $limit   = min((int)$request->query('limit', 5), 5);
        $country = $request->query('country', 'MY');
        $lang    = $request->query('lang', 'ms');
        $bLat    = $request->query('lat');
        $bLng    = $request->query('lng');

        $params = [
            'query'    => $q,
            'limit'    => $limit,
            'country'  => $country,
            'language' => $lang,
            'layers'   => 'address,street,place',
        ];
        if ($bLat !== null && $bLng !== null) {
            $params['near'] = "{$bLat},{$bLng}"; // bias near current marker
        }

        $cacheKey = 'radar_fwd_' . md5(json_encode($params));

        try {
            $result = Cache::remember($cacheKey, 15 * 60, function () use ($params) {
                $resp = $this->radar()->get('https://api.radar.io/v1/geocode/forward', $params);

                if ($resp->failed()) {
                    return ['error' => true, 'status' => $resp->status(), 'body' => $resp->json()];
                }
                return ['error' => false, 'status' => 200, 'body' => $resp->json()];
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Radar transport error', 'error' => $e->getMessage()], 502);
        }

        if ($result['error']) {
            return response()->json(['message' => 'Radar forward failed', 'body' => $result['body']], $result['status']);
        }

        $addresses = $result['body']['addresses'] ?? [];
        $items = collect($addresses)->map(function ($a) {
            return [
                'label' => $a['formattedAddress'] ?? '',
                'lat'   => $a['latitude'] ?? null,
                'lng'   => $a['longitude'] ?? null,
                'components' => $this->extractComponents($a),
            ];
        })->values();

        // First address components for convenience (UI may not need to dig into raw)
        $first = $addresses[0] ?? null;

        return response()->json([
            'results'    => $items,
            'components' => $this->extractComponents($first),
            'raw'        => $result['body'],
        ]);
    }
}