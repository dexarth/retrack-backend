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

    /**
     * @OA\Get(
     *     path="/api/form-show/{formName}/{id}",
     *     tags={"Listing"},
     *     summary="Fetch single record (multi-table or single-table)",
     *     description="Returns single record based on a form relation (multi-table) or from a single table if no relation is found.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="formName",
     *         in="path",
     *         required=true,
     *         description="Form name (from table_relations) or table name (for single table)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Primary record ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Record retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Model or record not found"
     *     )
     * )
     */
    public function getSingleRecord($formName, $id)
    {
        // Check if this is a form relation
        $relation = DB::table('table_relations')->where('form_name', $formName)->first();

        if (!$relation) {
            // fallback to single-table model
            $modelClass = $this->resolveModelFromTable($formName);

            if (!$modelClass || !class_exists($modelClass)) {
                return response()->json(['error' => 'Model not found.'], 404);
            }

            $record = $modelClass::find($id);

            return $record
                ? response()->json(['data' => $record])
                : response()->json(['error' => 'Record not found.'], 404);
        }

        // Primary table setup
        $primaryTable = $relation->primary_table;
        $primaryKey = $relation->primary_column ?? 'id';

        $primaryModel = $this->resolveModelFromTable($primaryTable);
        $primaryRecord = $primaryModel::find($id);

        if (!$primaryRecord) {
            return response()->json(['error' => 'Primary record not found.'], 404);
        }

        $responseData = [
            $primaryTable => $primaryRecord->toArray(),
        ];

        // Fetch related tables if any
        $relatedTables = DB::table('table_relations')
            ->where('form_name', $formName)
            ->whereNotNull('related_table')
            ->get();

        foreach ($relatedTables as $rel) {
            $relatedModel = $this->resolveModelFromTable($rel->related_table);
            $relatedRecord = $relatedModel::where($rel->foreign_key, $primaryRecord->{$rel->primary_column})->first();

            if ($relatedRecord) {
                $responseData[$rel->related_table] = $relatedRecord->toArray();
            }
        }

        return response()->json(['data' => $responseData]);
    }

    protected function resolveModelFromTable(string $table): ?string
    {
        // Manual mapping
        $map = [
            'menus' => \App\Models\Menu::class,
            'users' => \App\Models\User::class,
            'mentors' => \App\Models\Mentor::class,
            'mentees' => \App\Models\Mentee::class,
            'categories' => \App\Models\Category::class,
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
