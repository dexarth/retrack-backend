<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ListingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/listing/{table}",
     *     tags={"Listing"},
     *     summary="Get listing of a table",
     *     description="Returns all data from a whitelisted table using its associated model.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="table",
     *         in="path",
     *         required=true,
     *         description="The name of the table to list data from (must be in the allowed list)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Table not allowed"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Model not found"
     *     )
     * )
     */
    public function getListing(Request $request, $table)
    {
        // Whitelisted tables from DB based on 'read' permission
        $allowedTables = DB::table('allowed_tables')
            ->where('read', true)
            ->pluck('table_name')
            ->toArray();

        if (!in_array($table, $allowedTables)) {
            return response()->json(['error' => 'Table not allowed.'], 403);
        }

        // Resolve model class
        $modelClass = $this->resolveModelFromTable($table);

        if (!$modelClass || !class_exists($modelClass)) {
            return response()->json(['error' => 'Model not found for this table.'], 404);
        }

        try {
            $data = $modelClass::all();
            return response()->json(['data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error fetching data: ' . $e->getMessage()], 500);
        }
    }


    protected function resolveModelFromTable(string $table): ?string
    {
        // Manual mapping
        $map = [
            'menus'    => \App\Models\Menu::class,
        ];

        // Check if manually mapped
        if (isset($map[$table])) {
            return $map[$table];
        }

        // Try dynamic resolution (e.g., 'menus' => 'App\Models\Menu')
        $singular = Str::studly(Str::singular($table));
        $modelClass = "App\\Models\\$singular";

        return class_exists($modelClass) ? $modelClass : null;
    }

}
