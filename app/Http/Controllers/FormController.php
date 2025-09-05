<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Http\Controllers\NotificationController;
use App\Models\Laporan;
use App\Models\HealthMonitoring;
use App\Models\LaporDiri;
use App\Models\Mentee;
use App\Models\User;
use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Support\Facades\Log;

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
                $path = $value->store("uploads/{$table}/{$field}", 's3');
                Storage::disk('s3')->setVisibility($path, 'public'); // ✅ ADD THIS
                $paths[$field] = $path;
            } elseif (is_array($value)) {
                $paths[$field] = [];
                foreach ($value as $file) {
                    if ($file instanceof UploadedFile && $file->isValid()) {
                        $path = $file->store("uploads/{$table}/{$field}", 's3');
                        Storage::disk('s3')->setVisibility($path, 'public'); // ✅ ADD THIS
                        $paths[$field][] = $path;
                    }
                }
            }
        }

        return $paths;
    }


    public function submitForm(Request $request, string $formName)
    {
        $this->validateFormLimitations($formName, $request);

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

            //trigger notification
            NotificationController::trigger(
                $relation->primary_table,
                $primary
            );

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
    public function updateForm(Request $request, string $formName, string $routeId)
    {
        // Decode public_id → real int id
        $routeId = is_numeric($routeId) ? (int) $routeId : (Hashids::decode($routeId)[0] ?? null);
        Log::info('updateForm', ['form'=>$formName, 'routeId'=>$routeId]);
        if (!$routeId) {
            return response()->json(['error' => 'Invalid ID'], 404);
        }

        $this->validateFormLimitations($formName, $request, $routeId); 

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

            $primaryId = $routeId ?? $primaryData[$primaryColumn];
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

    // In your FormController

    /**
     * Validate business rules for specific forms.
     * Will throw a JSON response if invalid.
     */
    private function validateFormLimitations(string $formName, Request $request, ?int $routeId = null)
    {
        // === Mentee: unique email & id prospek only ===
        if ($formName === 'form-mentees') {
            $menteeData = (array) $request->input('mentees', []);
            $userData   = (array) $request->input('users',   []);

            // incoming ids (when editing)
            $menteeId = data_get($menteeData, 'id');          // mentees.id (if editing)
            $userId   = data_get($userData, 'id')             // users.id (if editing)
                    ?? data_get($menteeData, 'user_id');     // or from mentee record if you have it

            $prospekId    = trim((string) data_get($menteeData, 'id_prospek'));
            $prospekEmail = trim((string) data_get($userData,   'email'));

            if ($prospekId === '') {
                return response()->json(['message' => 'ID prospek tidak dijumpai.'], 400);
            }
            if ($prospekEmail === '') {
                return response()->json(['message' => 'Email tidak dijumpai.'], 400);
            }

            // ---- unique checks ----
            // (A) id_prospek unique in mentees table
            $existsProspekId = Mentee::where('id_prospek', $prospekId)
                ->when($menteeId, fn($q) => $q->where('id', '!=', $menteeId))
                ->exists();

            // (B) email unique in users table (case-insensitive)
            $existsProspekEmail = User::whereRaw('LOWER(email) = LOWER(?)', [$prospekEmail])
                ->when($userId, fn($q) => $q->where('id', '!=', $userId))
                ->exists();

            if ($existsProspekId) {
                abort(response()->json(['message' => 'Maaf, prospek dengan SMPP tersebut sudah ada.'], 422));
            }
            if ($existsProspekEmail) {
                abort(response()->json(['message' => 'Maaf, prospek dengan Emel tersebut sudah ada.'], 422));
            }
        }

        // === Mentors : unique email only ===
        if ($formName === 'form-mentors') {
            $mentorData = (array) $request->input('mentors', []);
            $userData   = (array) $request->input('users',   []);

            // prefer explicit users.id; fallback to mentors.user_id; then route id
            $userId = data_get($userData, 'id')
                ?? data_get($mentorData, 'user_id')
                ?? $routeId;

            $email  = trim((string) data_get($userData, 'email'));
            if ($email === '') {
                return response()->json(['message' => 'Email tidak dijumpai.'], 400);
            }

            $exists = User::whereRaw('LOWER(email) = LOWER(?)', [$email])
                ->when($userId, fn($q) => $q->where('id', '!=', $userId))
                ->exists();

            if ($exists) {
                abort(response()->json(['message' => 'Maaf, email tersebut sudah digunakan.'], 422));
            }
        }

        // === Admins : unique email only ===
        if ($formName === 'form-admins') {
            $adminData = (array) $request->input('admins', []);
            $userData  = (array) $request->input('users',  []);

            // prefer explicit users.id; fallback to admins.user_id; then route id
            $userId = data_get($userData, 'id')
                ?? data_get($adminData, 'user_id')
                ?? $routeId;

            $email  = trim((string) data_get($userData, 'email'));
            if ($email === '') {
                return response()->json(['message' => 'Email tidak dijumpai.'], 400);
            }

            $exists = User::whereRaw('LOWER(email) = LOWER(?)', [$email])
                ->when($userId, fn($q) => $q->where('id', '!=', $userId))
                ->exists();

            if ($exists) {
                abort(response()->json(['message' => 'Maaf, email tersebut sudah digunakan.'], 422));
            }
        }

        // === Laporan Mentee ===
        if ($formName === 'laporan-mentee') {
            $payload = (array) $request->input('laporan_mentee', []);
            if (empty($payload)) {
                $payload = (array) $request->input('laporan', []); // <-- your React code sends this
            }

            // mentee_id is required; do NOT silently fall back to auth()->id()
            $menteeId = data_get($payload, 'mentee_id');
            if (!$menteeId || !is_numeric($menteeId)) {
                return response()->json(['message' => 'ID mentee tidak dijumpai atau tidak sah.'], 400);
            }

            $exists = Laporan::where('mentee_id', $menteeId)
                ->whereDate('created_at', now()->toDateString())
                ->when($routeId, fn($q) => $q->where('id','!=',$routeId))
                //->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                abort(response()->json(['message' => 'Maaf, anda hanya dibenarkan menghantar satu laporan sehari.'], 422));
            }
        }

        // === Health Monitoring ===
        if ($formName === 'form-health') {
            $payload = (array) $request->input('health_monitorings', []);
            $menteeId = data_get($payload, 'mentee_id')
                ?? auth()->id();

            if (!$menteeId || !is_numeric($menteeId)) {
                abort(response()->json(['message' => 'ID mentee tidak dijumpai atau tidak sah.'], 400));
            }

            $exists = HealthMonitoring::where('mentee_id', $menteeId)
                ->whereDate('created_at', now()->toDateString())
                ->when($routeId, fn($q) => $q->where('id','!=',$routeId))
                //->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                abort(response()->json(['message' => 'Maaf, anda hanya dibenarkan menghantar satu laporan kesihatan sehari.'], 422));
            }
        }

        // === Lapor Diri ===
        if ($formName === 'lapor-diri') {
            $payload   = (array) $request->input('lapordiri', []);
            $tempat    = data_get($payload, 'tempat');
            $menteeId  = data_get($payload, 'mentee_id');
            $laporRaw  = trim((string) data_get($payload, 'lapor_diri_pada', ''));

            if (!$tempat || !$menteeId || $laporRaw === '') {
                abort(response()->json(['message' => 'Data tidak lengkap untuk Lapor Diri.'], 400));
            }

            try {
                $raw = $laporRaw;
                if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $raw)) $raw = str_replace('T',' ',$raw).':00';
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw))  $raw .= ':00';
                $dt = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $raw);
                if ($dt === false) throw new \Exception('Bad datetime');
            } catch (\Throwable $e) {
                abort(response()->json(['message' => 'Tarikh & Masa Lapor Diri tidak sah.'], 422));
            }

            $dateOnly = $dt->toDateString();
            $sqlDate  = $dt->format('Y-m-d H:i:s');

            // (A) tempat + exact datetime
            $existsSlot = LaporDiri::where('tempat', $tempat)
                ->where('lapor_diri_pada', $sqlDate)
                ->when($routeId, fn($q) => $q->where('id','!=',$routeId))
                //->whereNull('deleted_at')
                ->exists();

            if ($existsSlot) {
                abort(response()->json([
                    'message' => 'Sila pilih slot lain. Tarikh dan masa tersebut tidak tersedia di tempat tersebut.'
                ], 422));
            }

            // (B) one per mentee per date
            $menteeExists = LaporDiri::where('mentee_id', $menteeId)
                ->whereDate('lapor_diri_pada', $dateOnly)
                ->when($routeId, fn($q) => $q->where('id','!=',$routeId))
                //->whereNull('deleted_at')
                ->exists();

            if ($menteeExists) {
                abort(response()->json([
                    'message' => 'Mentee telah mempunyai rekod Lapor Diri pada tarikh ini. Sila kemas kini rekod Lapor Diri yang sedia ada.'
                ], 422));
            }
        }
    }

}
