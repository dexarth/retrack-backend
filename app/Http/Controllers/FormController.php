<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Http\Controllers\NotificationController;
use App\Models\Laporan;

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
    private function storeAndMapFiles(array $files, string $table): array
    {
        $paths = [];

        foreach ($files as $field => $value) {
            if ($value instanceof UploadedFile && $value->isValid()) {
                $paths[$field] = $value->store("uploads/{$table}/{$field}", 'public');
            } elseif (is_array($value)) {
                $paths[$field] = [];
                foreach ($value as $file) {
                    if ($file instanceof UploadedFile && $file->isValid()) {
                        $paths[$field][] = $file->store("uploads/{$table}/{$field}", 'public');
                    }
                }
            }
        }

        return $paths;
    }

    public function submitForm(Request $request, string $formName)
    {
        if ($formName === 'laporan-mentee') {
            $menteeId = $request->input('mentee_id')
                ?? $request->input('laporan_mentee.mentee_id')
                ?? auth()->id();

            if (!$menteeId) {
                return response()->json(['message' => 'ID mentee tidak dijumpai.'], 400);
            }

            $exists = Laporan::where('mentee_id', $menteeId)
                ->whereDate('created_at', today())
                ->exists();

            if ($exists) {
                return response()->json(['message' => 'Maaf, anda hanya dibenarkan menghantar satu laporan sehari.'], 422);
            }
        }

        if ($formName === 'lapor-diri') {
            $tarikh = $request->input('lapordiri.tarikh');
            $masa = $request->input('lapordiri.masa');
            $tempat = $request->input('lapordiri.tempat');

            $exists = DB::table('lapordiri')
                ->where('tempat', $tempat)
                ->where('tarikh', $tarikh)
                ->where('masa', $masa)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Sila pilih slot yang lain. Lokasi bagi tarikh dan masa tersebut tidak tersedia.'
                ], 422);
            }
        }

        $relation = DB::table('table_relations')->where('form_name', $formName)->first();

        if (!$relation) {
            return response()->json(['error' => 'Form relation not defined.'], 400);
        }

        DB::beginTransaction();

        try {
            // === PRIMARY ===
            $primaryData = $request->input($relation->primary_table, []);
            $primaryFiles = $request->hasFile($relation->primary_table)
                ? $request->file($relation->primary_table)
                : [];
            $primaryData = array_merge(
                $primaryData,
                $this->storeAndMapFiles($primaryFiles, $relation->primary_table)
            );

            if (isset($primaryData['password'])) {
                $primaryData['password'] = bcrypt($primaryData['password']);
            }

            $primaryModel = $this->resolveModelFromTable($relation->primary_table);
            $primary = $primaryModel::create($primaryData);

            NotificationController::trigger($relation->primary_table, $primary);

            // === RELATED ===
            if ($relation->related_table) {
                $relatedData = $request->input($relation->related_table, []);
                $relatedFiles = $request->hasFile($relation->related_table)
                    ? $request->file($relation->related_table)
                    : [];
                $relatedData = array_merge(
                    $relatedData,
                    $this->storeAndMapFiles($relatedFiles, $relation->related_table)
                );

                $relatedData[$relation->foreign_key] = $primary->{$relation->primary_column};

                if ($relation->field_copy_map) {
                    $copies = json_decode($relation->field_copy_map, true);
                    foreach ($copies as $relatedField => $sourceField) {
                        $relatedData[$relatedField] = $primary->{$sourceField};
                    }
                }

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
     *     description="Handles dynamic form update involving multiple related tables using configured table_relations. Tracks changes and supports file uploads.",
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
     *         description="Form data including files and fields for both primary and related tables",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="users[name]", type="string", example="Ali Updated"),
     *                 @OA\Property(property="users[email]", type="string", example="ali_updated@example.com"),
     *                 @OA\Property(property="users[avatar]", type="string", format="binary"),
     *                 @OA\Property(property="mentors[pangkat]", type="string", example="SARJAN UPDATED"),
     *                 @OA\Property(property="mentors[parol_daerah]", type="string", example="BEAUFORT UPDATED"),
     *                 @OA\Property(property="mentors[profile_picture]", type="string", format="binary")
     *             )
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

        DB::beginTransaction();

        try {
            $primaryTable = $relationConfig->first()->primary_table;
            $primaryColumn = $relationConfig->first()->primary_column ?? 'id';

            $payload = $request->all();
            $primaryData = $payload[$primaryTable] ?? null;

            if (!$primaryData) {
                $primaryData = [];

                foreach ($request->all() as $key => $value) {
                    if (preg_match('/^' . preg_quote($primaryTable) . '\[(.+?)\]$/', $key, $matches)) {
                        $field = $matches[1];
                        $primaryData[$field] = $value;
                    }
                }
            }

            // Process file uploads for primary table
            $primaryFiles = $request->hasFile($primaryTable) ? $request->file($primaryTable) : [];

            if (!empty($primaryFiles)) {
                $primaryData = array_merge($primaryData, $this->storeAndMapFiles($primaryFiles, $primaryTable));
            }

            if (!$primaryData) {
                return response()->json(['error' => "Missing data for primary table: $primaryTable"], 400);
            }

            $primaryModelClass = $this->resolveModelFromTable($primaryTable);
            if (!$primaryModelClass) {
                return response()->json(['error' => "Model not found for table: $primaryTable"], 404);
            }

            $primaryId = $primaryData[$primaryColumn] ?? $routeId;
            $existingPrimaryModel = $primaryModelClass::find($primaryId);

            if (!$existingPrimaryModel) {
                return response()->json(['error' => "Primary record not found."], 404);
            }

            $this->logChanges($formName, $primaryTable, $primaryId, $existingPrimaryModel->toArray(), $primaryData);
            $existingPrimaryModel->update($primaryData);

            // Related tables
            foreach ($relationConfig as $relation) {
                if (!$relation->related_table) continue;

                $relatedData = $payload[$relation->related_table] ?? null;
                $relatedFiles = $request->hasFile($relation->related_table)
                    ? $request->file($relation->related_table)
                    : [];

                if ($relatedFiles) {
                    $relatedData = array_merge($relatedData ?? [], $this->storeAndMapFiles($relatedFiles, $relation->related_table));
                }

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
        $map = DB::table('model_mappings')->where('key', $table)->value('model_class');

        if (isset($map[$table])) {
            return $map[$table];
        }

        // Dynamically infer model class name
        $singular = Str::studly(Str::singular($table));
        $modelClass = "App\\Models\\$singular";

        return class_exists($modelClass) ? $modelClass : null;
    }

}
