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
     *     path="/api/form-submit/{formName}",
     *     tags={"Form"},
     *     summary="Submit data for a multi-table form",
     *     description="Handles dynamic form submission involving multiple related tables using configured table_relations.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="formName",
     *         in="path",
     *         required=true,
     *         description="The identifier for the form defined in table_relations (e.g., mentor_form)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Payload should contain objects for each table defined in the relation. The primary table data and related table data must be included.",
     *         @OA\JsonContent(
     *             example={
     *                 "users": {
     *                     "name": "Ali",
     *                     "email": "ali@example.com",
     *                     "password": "secret123"
     *                 },
     *                 "mentors": {
     *                     "pangkat": "SARJAN",
     *                     "parol_daerah": "BEAUFORT"
     *                 }
     *             }
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
     *         response=400,
     *         description="Form relation not defined or invalid data structure",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Form relation not defined.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Model class not found for a table",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Model not found for this table.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to submit data due to a server error or DB constraint",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Submission failed"),
     *             @OA\Property(property="details", type="string", example="SQLSTATE[23000]: Integrity constraint violation...")
     *         )
     *     )
     * )
     */
    public function submitForm(Request $request, string $formName)
    {
        $relation = DB::table('table_relations')->where('form_name', $formName)->first();

        if (!$relation) {
            return response()->json(['error' => 'Form relation not defined.'], 400);
        }

        DB::beginTransaction();

        try {
            // 1. Create primary table record (e.g., users)
            $primaryData = $request->input($relation->primary_table);
            if (isset($primaryData['password'])) {
                $primaryData['password'] = bcrypt($primaryData['password']);
            }

            $primaryModel = $this->resolveModelFromTable($relation->primary_table);
            $primary = $primaryModel::create($primaryData);

            // 2. Check if related table exists
            if ($relation->related_table) {
                $relatedData = $request->input($relation->related_table);
                $relatedData[$relation->foreign_key] = $primary->{$relation->primary_column};

                // Copy fields if needed
                if ($relation->field_copy_map) {
                    $copies = json_decode($relation->field_copy_map, true);
                    foreach ($copies as $relatedField => $sourceField) {
                        $relatedData[$relatedField] = $primary->{$sourceField};
                    }
                }

                // Create related table record
                $relatedModel = $this->resolveModelFromTable($relation->related_table);
                $relatedModel::create($relatedData);
            }

            DB::commit();
            return response()->json(['message' => 'Data submitted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Submission failed',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/form-submit/{formName}/{id}",
     *     tags={"Form"},
     *     summary="Update data for a multi-table form",
     *     description="Handles dynamic form update involving multiple related tables using configured table_relations. Tracks changes with a log.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="formName",
     *         in="path",
     *         required=true,
     *         description="The identifier for the form defined in table_relations (e.g., mentor_form)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The ID of the primary record to update",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Payload containing objects for each related table defined in the form relation. Includes primary and related table data for update.",
     *         @OA\JsonContent(
     *             example={
     *                 "users": {
     *                     "name": "Ali Updated",
     *                     "email": "ali_updated@example.com"
     *                 },
     *                 "mentors": {
     *                     "pangkat": "SARJAN UPDATED",
     *                     "parol_daerah": "BEAUFORT UPDATED"
     *                 }
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Data updated successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Form relation not defined or invalid data structure",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Form relation not defined.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Model class or record not found for a table",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Model or record not found for this table.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to update data due to a server error or DB constraint",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Update failed"),
     *             @OA\Property(property="details", type="string", example="SQLSTATE[23000]: Integrity constraint violation...")
     *         )
     *     )
     * )
     */
    public function updateForm(Request $request, string $formName, int $routeId)
    {
        $relationConfig = DB::table('table_relations')->where('form_name', $formName)->get();

        if ($relationConfig->isEmpty()) {
            return response()->json(['error' => 'Form relation not defined.'], 400);
        }

        $payload = $request->all();
        info('Request All:', $request->all());
        DB::beginTransaction();

        try {
            // Step 1: Get primary table info
            $primaryTable = $relationConfig->first()->primary_table;
            $primaryColumn = $relationConfig->first()->primary_column ?? 'id';
            $primaryData = $payload[$primaryTable] ?? null;

            if (!$primaryData) {
                return response()->json(['error' => "Missing data for primary table: $primaryTable"], 400);
            }

            // Step 2: Resolve model class
            $primaryModelClass = $this->resolveModelFromTable($primaryTable);
            if (!$primaryModelClass) {
                return response()->json(['error' => "Model not found for table: $primaryTable"], 404);
            }

            // Step 3: Fetch existing record by primary_column
            $primaryId = $primaryData[$primaryColumn] ?? $routeId;
            $existingPrimaryModel = $primaryModelClass::find($primaryId);

            if (!$existingPrimaryModel) {
                return response()->json(['error' => "Primary record not found."], 404);
            }

            // Step 4: Log changes
            $this->logChanges($formName, $primaryTable, $primaryId, $existingPrimaryModel->toArray(), $primaryData);

            // Step 5: Update record
            $existingPrimaryModel->update($primaryData);

            // Step 6: Handle related tables (optional)
            foreach ($relationConfig as $relation) {
                if (!$relation->related_table) continue;

                $relatedData = $payload[$relation->related_table] ?? null;
                if (!$relatedData) continue;

                $relatedModelClass = $this->resolveModelFromTable($relation->related_table);
                if (!$relatedModelClass) continue;

                $foreignKey = $relation->foreign_key;
                $relatedRecord = $relatedModelClass::where($foreignKey, $primaryId)->first();

                if (!$relatedRecord) continue;

                $this->logChanges($formName, $relation->related_table, $relatedRecord->id, $relatedRecord->toArray(), $relatedData);
                $relatedRecord->update($relatedData);
            }

            DB::commit();

            return response()->json(['message' => 'Data updated successfully.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Update failed', 'details' => $e->getMessage()], 500);
        }
    }


    protected function logChanges(string $form, string $table, int $id, array $old, array $new): void
    {
        $changes = [];

        foreach ($new as $key => $newValue) {
            if (!array_key_exists($key, $old)) continue;

            $oldValue = $old[$key];
            if ($oldValue != $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        if (!empty($changes)) {
            DB::table('form_update_logs')->insert([
                'form_name' => $form,
                'table_name' => $table,
                'record_id' => $id,
                'changes' => json_encode($changes),
                'updated_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }



    protected function resolveModelFromTable(string $table): ?string
    {
        // Manual override
        $map = [
            'menus' => \App\Models\Menu::class,
            'users' => \App\Models\User::class,
            'mentors' => \App\Models\Mentor::class,
            'mentees' => \App\Models\Mentee::class,
            'categories' => \App\Models\Category::class,
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
