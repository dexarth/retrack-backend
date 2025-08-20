<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GeoController extends Controller
{
    public function reverse(Request $request)
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');

        if (!$lat || !$lng) {
            return response()->json(['message' => 'lat/lng required'], 422);
        }

        $resp = Http::withHeaders([
            'Authorization' => config('services.radar.secret'),
        ])->get('https://api.radar.io/v1/geocode/reverse', [
            'coordinates' => "{$lat},{$lng}",
        ]);

        if ($resp->failed()) {
            return response()->json(['message' => 'Radar API error', 'body' => $resp->json()], 502);
        }

        $json = $resp->json();
        $address = $json['addresses'][0]['formattedAddress'] ?? null;

        return response()->json([
            'formattedAddress' => $address,
            'raw' => $json,
        ]);
    }

    public function forward(Request $request)
    {
        $q        = $request->query('q');
        $limit    = (int) $request->query('limit', 5);
        $country  = $request->query('country');   // e.g. MY
        $lang     = $request->query('lang');      // e.g. ms
        $biasLat  = $request->query('lat');       // optional bias
        $biasLng  = $request->query('lng');       // optional bias

        if (!$q) {
            return response()->json(['message' => 'q required'], 422);
        }

        $params = [
            'query' => $q,
            'limit' => $limit,
        ];
        if ($country) $params['country']  = $country;
        if ($lang)    $params['language'] = $lang;
        if ($biasLat && $biasLng) $params['location'] = "{$biasLat},{$biasLng}"; // bias results

        $resp = Http::withHeaders([
            'Authorization' => config('services.radar.secret'),
        ])->get('https://api.radar.io/v1/geocode/forward', $params);

        if ($resp->failed()) {
            return response()->json(['message' => 'Radar forward failed', 'body' => $resp->json()], 502);
        }

        $json = $resp->json();
        $items = collect($json['addresses'] ?? [])->map(fn($a) => [
            'label' => $a['formattedAddress'] ?? '',
            'lat'   => $a['latitude'] ?? null,
            'lng'   => $a['longitude'] ?? null,
        ])->values();

        return response()->json([
            'results' => $items,
            'raw'     => $json,
        ]);
    }
}

