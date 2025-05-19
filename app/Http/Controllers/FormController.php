<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FormController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/form/submit/{table}",
     *     tags={"Form"},
     *     summary="Submit new record to a dynamic table",
     *     description="Submits a new record to a whitelisted table with create permission using its associated model.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="table",
     *         in="path",
     *         required=true,
     *         description="The target table name (must be allowed and have 'create' permission)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Form fields (dynamic, depends on the table/model)",
     *         @OA\JsonContent(
     *             example={"name": "Sample Name", "description": "Some description"}
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Data submitted successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Table not allowed or create permission denied",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Table not allowed or create permission denied.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Model not found for this table",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Model not found for this table.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to submit data",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to submit data."),
     *             @OA\Property(property="details", type="string", example="SQLSTATE[23000]: Integrity constraint violation...")
     *         )
     *     )
     * )
     */
    public function submit(Request $request, $table)
    {
        $isAllowed = DB::table('allowed_tables')
            ->where('table_name', $table)
            ->where('create', true)
            ->exists();

        if (!$isAllowed) {
            return response()->json(['error' => 'Table not allowed or create permission denied.'], 403);
        }

        //Resolve model class
        $modelClass = $this->resolveModelFromTable($table);
        if (!$modelClass || !class_exists($modelClass)) {
            return response()->json(['error' => 'Model not found for this table.'], 404);
        }

        try {
            //Use model to create record
            $model = new $modelClass();

            // Filter only fillable attributes (mass-assignment protection)
            $fillable = $model->getFillable();
            $data = $request->only($fillable);

            $model->fill($data);
            $model->save();

            return response()->json(['message' => 'Data submitted successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to submit data.', 'details' => $e->getMessage()], 500);
        }
    }

    protected function resolveModelFromTable(string $table): ?string
    {
        // Manual override
        $map = [
            'menus' => \App\Models\Menu::class,
        ];

        if (isset($map[$table])) {
            return $map[$table];
        }

        // Dynamically infer model class name
        $singular = Str::studly(Str::singular($table));
        $modelClass = "App\\Models\\$singular";

        return class_exists($modelClass) ? $modelClass : null;
    }
    
}
