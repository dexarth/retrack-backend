<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            if ($table === 'blogs') {
                $data = $modelClass::with('blog_category.category')->get();
            }else if ($table === 'mentors') {
                $data = $modelClass::withCount('mentees')->get();
            }else if ($table === 'lapordiri' || $table === 'laporan') {
                $data = $modelClass::with(['mentor', 'mentee'])->get();
            }
            else {
                $data = $modelClass::all();
            }
            return response()->json(['data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error fetching data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/listing-filter/{table}",
     *     operationId="getListingWithFilter",
     *     tags={"Listing"},
     *     summary="Get filtered listing of a table",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="table",
     *         in="path",
     *         required=true,
     *         description="Whitelisted table name",
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filters[role]",
     *         in="query",
     *         required=false,
     *         description="Filter by role",
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=403, description="Table not allowed"),
     *     @OA\Response(response=404, description="Model not found")
     * )
     */
    public function getListingWithFilter(Request $request, $table)
    {
        $allowed = DB::table('allowed_tables')->where('read', true)->pluck('table_name')->toArray();
        if (!in_array($table, $allowed)) {
            return response()->json(['error'=>'Table not allowed.'], 403);
        }

        $modelClass = $this->resolveModelFromTable($table);
        if (!$modelClass || !class_exists($modelClass)) {
            return response()->json(['error'=>'Model not found.'], 404);
        }

        $query = $modelClass::query();

        // grab only the filters[...] query parameters
        $filters = $request->get('filters', []);
        foreach ($filters as $column => $value) {
            // optionally: guard against invalid columns
            if (Schema::hasColumn((new $modelClass)->getTable(), $column)) {
                $query->where($column, $value);
            }
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * @OA\Get(
     *     path="/api/listing-join-filter/{table}",
     *     tags={"Listing"},
     *     summary="Get listing with dynamic joins and filters",
     *     description="Returns records from a table with optional relationship joins, filters, and sorting. Relationships must be defined in the Eloquent model.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="table",
     *         in="path",
     *         required=true,
     *         description="Name of the whitelisted table (e.g., 'laporan', 'mentee')",
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="with[]",
     *         in="query",
     *         required=false,
     *         description="Relationship(s) to eager load (e.g., mentee, mentor)",
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(type="string")
     *         )
     *     ),
     *
     *     @OA\Parameter(
     *         name="filters[column]",
     *         in="query",
     *         required=false,
     *         description="Filter records by column values (e.g., filters[mentee_id]=25)",
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         required=false,
     *         description="Column to sort by (e.g., 'created_at')",
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="order",
     *         in="query",
     *         required=false,
     *         description="Sort direction (asc or desc). Default is asc.",
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=403, description="Table not allowed"),
     *     @OA\Response(response=404, description="Model not found")
     * )
     */
    public function getListingJoinFilter(Request $request, $table)
    {
        // Reuse the allowed table check
        $allowed = DB::table('allowed_tables')->where('read', true)->pluck('table_name')->toArray();
        if (!in_array($table, $allowed)) {
            return response()->json(['error' => 'Table not allowed.'], 403);
        }

        // Resolve model
        $modelClass = $this->resolveModelFromTable($table);
        if (!$modelClass || !class_exists($modelClass)) {
            return response()->json(['error' => 'Model not found.'], 404);
        }

        // Start building query
        $query = $modelClass::query();

        // Automatically eager load allowed relationships from query ?with[]=mentee&with[]=mentor
        if ($request->has('with')) {
            foreach ((array) $request->get('with') as $relation) {
                if (method_exists($modelClass, $relation)) {
                    $query->with($relation);
                }
            }
        }

        // Apply filters: ?filters[column]=value
        $filters = $request->get('filters', []);
        foreach ($filters as $column => $value) {
            if (Schema::hasColumn((new $modelClass)->getTable(), $column)) {
                $query->where($column, $value);
            }
        }

        // Sorting: ?sort=created_at&order=desc
        if ($request->filled('sort')) {
            $query->orderBy($request->get('sort'), $request->get('order', 'asc'));
        }

        return response()->json(['data' => $query->get()]);
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
        $map = DB::table('model_mappings')->where('key', $table)->value('model_class');

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
