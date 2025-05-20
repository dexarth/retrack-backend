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



    protected function resolveModelFromTable(string $table): ?string
    {
        // Manual override
        $map = [
            'menus' => \App\Models\Menu::class,
            'users' => \App\Models\User::class,
            'mentors' => \App\Models\Mentor::class,
            'mentees' => \App\Models\Mentee::class,
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
