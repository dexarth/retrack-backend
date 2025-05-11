<?php

namespace App\Http\Controllers;

use App\Models\Weather;
use Illuminate\Http\Request;

class WeatherController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/weather/{city}",
     *     summary="Get weather by city",
     *     description="Returns weather data for the given city.",
     *     tags={"Weather"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="city",
     *         in="path",
     *         description="City name",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Weather data for the city",
     *         @OA\JsonContent(type="object", 
     *             @OA\Property(property="city", type="string", example="New York"),
     *             @OA\Property(property="temperature", type="number", format="float", example=28.5),
     *             @OA\Property(property="description", type="string", example="Clear sky")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="City not found"
     *     )
     * )
     */
    public function getWeatherByCity($city)
    {
        // Retrieve weather data from the database for the city
        $weather = Weather::where('city', $city)->first();

        if ($weather) {
            return response()->json($weather);
        } else {
            return response()->json(['message' => 'City not found'], 404);
        }
    }
}