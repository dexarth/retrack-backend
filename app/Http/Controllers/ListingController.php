<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Mentee;

class ListingController extends Controller
{
  	protected function logCtx(string $msg, array $ctx = [], string $level = 'info'): void
    {
        $ctx = array_merge([
            'user_id' => auth()->id(),
            'env'     => app()->environment(),
            'path'    => request()->path(),
        ], $ctx);

        Log::$level($msg, $ctx);
    }
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
        $this->logCtx('getListing: entry', ['table' => $table, 'query' => $request->query()]);

        $allowedTables = DB::table('allowed_tables')
            ->where('read', true)
            ->pluck('table_name')
            ->toArray();

        if (!in_array($table, $allowedTables, true)) {
            $this->logCtx('getListing: table not allowed', ['table' => $table], 'warning');
            return response()->json(['error' => 'Table not allowed.'], 403);
        }

        $modelClass = $this->resolveModelFromTable($table);
        $this->logCtx('getListing: resolved model', ['table' => $table, 'modelClass' => $modelClass]);

        if (!$modelClass || !class_exists($modelClass)) {
            $this->logCtx('getListing: model not found', ['table' => $table], 'error');
            return response()->json(['error' => 'Model not found for this table.'], 404);
        }

        try {
            $model   = new $modelClass;
            $pk      = $model->getKeyName();
            $tableDb = $model->getTable();
            $allCols = Schema::getColumnListing($tableDb);

            $this->logCtx('getListing: schema', ['tableDb' => $tableDb, 'pk' => $pk, 'colCount' => count($allCols)]);

            $requested = $request->query('columns');
            $selectCols = null;
            if ($requested) {
                $req  = array_filter(array_map('trim', explode(',', $requested)));
                $safe = array_values(array_intersect($req, $allCols));
                if (!in_array($pk, $safe, true)) $safe[] = $pk;
                if ($safe) $selectCols = $safe;
                $this->logCtx('getListing: sanitized columns', ['requested' => $req, 'selected' => $selectCols]);
            }

            $query = $modelClass::query();

            if ($table === 'blogs') {
                $query->with('blog_category.category');
            } elseif ($table === 'mentors') {
                $query->withCount('mentees')->with(['user:id,name']);
            } elseif (in_array($table, ['admins', 'mentees'], true)) {
                $query->with(['user:id,name']);
            } elseif (in_array($table, ['lapordiri', 'laporan', 'health_monitorings'], true)) {
                $query->with([
                    'mentorAccount:id,name',
                    'mentor:user_id,parol_daerah',
                    'mentee:user_id,id_prospek',
                    'menteeAccount:id,name',
                ]);
            } elseif ($table === 'staff_monitorings') {
                $query->with([
                    'user:id,name',
                    'mentorAccount:id,name',
                    'mentee:user_id,id_prospek,huraian_alamat,alamat_rumah',
                    'csi:id,nama_syarikat,huraian_alamat,alamat_syarikat'
                ]);
            }

            if ($selectCols) $query->select($selectCols);

            $orderBy  = $request->query('order_by');
            $orderDir = strtolower($request->query('order_dir', 'asc'));
            if ($orderBy && in_array($orderBy, $allCols, true)) {
                if (!in_array($orderDir, ['asc','desc'], true)) $orderDir = 'asc';
                $query->orderBy($orderBy, $orderDir);
            } else {
                $query->orderBy($pk, 'desc');
            }

            $data = $query->get();
            $this->logCtx('getListing: success', ['rowCount' => $data->count()]);

            return response()->json(['data' => $data]);
        } catch (Throwable $e) {
            $this->logCtx('getListing: exception', [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
            ], 'error');
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
        // Whitelist tables
        $allowed = DB::table('allowed_tables')->where('read', true)->pluck('table_name')->toArray();
        if (!in_array($table, $allowed, true)) {
            return response()->json(['error' => 'Table not allowed.'], 403);
        }

        // Resolve model
        $modelClass = $this->resolveModelFromTable($table);
        if (!$modelClass || !class_exists($modelClass)) {
            return response()->json(['error' => 'Model not found.'], 404);
        }

        $model       = new $modelClass;
        $tableName   = $model->getTable();
        $allCols     = Schema::getColumnListing($tableName);
        $primaryKey  = $model->getKeyName();

        $query = $modelClass::query();

        // ---- Columns: ?columns=id,nama_penuh ----
        $requested = $request->query('columns'); // string or null
        if ($requested) {
            $reqCols = array_values(array_filter(array_map('trim', explode(',', $requested))));
            // keep only valid columns
            $safeCols = array_values(array_intersect($reqCols, $allCols));
            // always ensure PK present (helps with relations/future ops)
            if (!in_array($primaryKey, $safeCols, true)) {
                $safeCols[] = $primaryKey;
            }
            if ($safeCols) {
                $query->select($safeCols);
            }
        }

        // ---- Filters: filters[col]=val or filters[col][]=a&filters[col][]=b ----
        $filters = (array) $request->get('filters', []);
        foreach ($filters as $column => $value) {
            if (!in_array($column, $allCols, true)) {
                continue; // ignore unknown columns
            }
            if (is_array($value)) {
                // supports whereIn for multi-values
                $query->whereIn($column, $value);
            } else {
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
     *         name="filters",
     *         in="query",
     *         required=false,
     *         description="Filter by any column (e.g. ?filters[mentee_id]=25&filters[status]=active)",
     *         @OA\Schema(
     *            type="object",
     *            additionalProperties=@OA\Schema(type="string")
     *         ),
     *         style="deepObject",
     *         explode=true
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
        $allowed = DB::table('allowed_tables')->where('read', true)->pluck('table_name')->toArray();
        if (!in_array($table, $allowed)) return response()->json(['error' => 'Table not allowed.'], 403);

        $modelClass = $this->resolveModelFromTable($table);
        if (!$modelClass || !class_exists($modelClass)) return response()->json(['error' => 'Model not found.'], 404);

        $query = $modelClass::query();

        // allowlist relations (use singular names that match your model methods)
        $allowedWith = [
            'laporan'            => ['mentor','mentee','mentorAccount'],
            'lapordiri'          => ['mentor','mentee','mentorAccount'],
            'health_monitorings' => ['mentor','mentee','menteeAccount','mentorAccount'],
            'staff_monitorings'  => ['mentor','mentee','csi','mentorAccount'],
            'blogs'              => ['blog_category'],
            'mentors'            => ['user'],
            'mentees'            => ['mentor','user'], // ✅ singular 'mentor'
        ];

        // parse ?with[]=mentor:user_id,parol_daerah&with[]=user:id,name
        $withReq = (array) $request->query('with', []);
        $with = [];
        foreach ($withReq as $item) {
            [$name, $cols] = array_pad(explode(':', $item, 2), 2, null);
            if (!in_array($name, $allowedWith[$table] ?? [], true)) continue;
            if (!method_exists($modelClass, $name)) continue;

            if ($cols) {
                $columns = array_filter(array_map('trim', explode(',', $cols)));
                $rel      = (new $modelClass)->{$name}();
                $related  = $rel->getRelated();
                $ownerKey = method_exists($rel, 'getOwnerKeyName') ? $rel->getOwnerKeyName() : $related->getKeyName();
                if (!in_array($ownerKey, $columns, true)) $columns[] = $ownerKey;

                // (optional) drop non-existent columns to avoid SQL 42S22
                $safe = array_values(array_unique(array_filter($columns, fn($c) => Schema::hasColumn($related->getTable(), $c))));
                $with[$name] = fn($q) => $q->select($safe ?: ['*']);
            } else {
                $with[] = $name;
            }
        }

        if (empty($with) && in_array($table, ['lapordiri','laporan','health_monitorings','staff_monitorings'], true)) {
            $with = ['mentor','mentee','menteeAccount', 'mentorAccount'];
        }

        if ($table === 'mentors') {
            $query->withCount('mentees');
        }
        if (!empty($with)) $query->with($with);

        // filters: support base columns AND relation.dot columns
        $filters = $request->input('filters', []);
        foreach ($filters as $column => $value) {
            if (strpos($column, '.') !== false) {
                // relation filter, e.g. mentor.parol_daerah
                [$rel, $col] = explode('.', $column, 2);
                if (in_array($rel, $allowedWith[$table] ?? [], true) && method_exists($modelClass, $rel)) {
                    $query->whereHas($rel, function($q) use ($col, $value) {
                        if (is_array($value) && array_key_first($value) === 'in') {
                            $q->whereIn($col, $value['in']);
                        } elseif (is_string($value) && str_starts_with($value, 'like:')) {
                            $q->where($col, 'like', substr($value, 5));
                        } elseif (is_string($value) && str_starts_with($value, '!=')) {
                            $q->where($col, '!=', substr($value, 2));
                        } else {
                            $q->where($col, $value);
                        }
                    });
                }
            } else {
                if (Schema::hasColumn((new $modelClass)->getTable(), $column)) {
                    $query->where($column, $value);
                }
            }
        }

        // optional date range + sort
        if ($request->filled('from')) $query->whereDate('updated_at', '>=', $request->input('from'));
        if ($request->filled('to'))   $query->whereDate('updated_at', '<=', $request->input('to'));
        if ($request->filled('sort')) $query->orderBy($request->get('sort'), $request->get('order', 'asc'));

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

    public function menteesLateSubmissions(Request $request)
    {
        $days = max(0, (int) $request->query('days', 2));

        // Scope by daerah: query ?daerah=... overrides admin’s daerah
        $daerah = $request->query('daerah')
            ?: DB::table('admins')->where('user_id', auth()->id())->value('parol_daerah');

        if (!$daerah) {
            return response()->json(['data' => []]);
        }

        // “Past N days” => no laporan with created_at >= cutoff
        $cutoff = now()->subDays($days)->startOfDay(); // adjust to endOfDay() if you want stricter window

        // Query
        $query = Mentee::query()
            ->with([
                'mentor:id,user_id,parol_daerah',
                // keep columns small; rely on accessor for profile_photo_url
                'user:id,name',
            ])
            ->whereHas('mentor', fn($q) => $q->where('parol_daerah', $daerah))
            ->whereDoesntHave('laporan', fn($q) => $q->where('created_at', '>=', $cutoff))
            ->select([
                'id','user_id','id_prospek','daerah','jantina','no_tel',
                'alamat_rumah','huraian_alamat','rumah_lat','rumah_long',
                'tarikh_bebas','mentor_id','kategori_prospek','jenis_penamatan',
                'nama_waris_1','no_tel_waris_1','nama_waris_2','no_tel_waris_2',
                'created_at','updated_at',
            ]);

        $rows = $query->get();

        // Ensure `user.profile_photo_url` exists in payload (Jetstream usually appends it)
        $data = $rows->map(function ($m) {
            $arr = $m->toArray();
            if (isset($m->user)) {
                $arr['user']['profile_photo_url'] = $m->user->profile_photo_url ?? $arr['user']['profile_photo_url'] ?? null;
            }
            return $arr;
        });

        return response()->json(['data' => $data]);
    }

    public function menteesLateSubmissionsForMentor(Request $request)
    {
        $days = max(0, (int) $request->query('days', 2));
        $mentorId = auth()->id(); // authenticated mentor user_id

        if (!$mentorId) return response()->json(['data' => []]);

        $cutoff = now()->subDays($days)->startOfDay();

        $mentees = \App\Models\Mentee::with(['user:id,name', 'mentor:user_id,parol_daerah'])
            ->where('mentor_id', $mentorId)
            ->whereDoesntHave('laporan', fn($q) => $q->where('created_at', '>=', $cutoff))
            ->get([
                'id','user_id','id_prospek','daerah','jantina','no_tel',
                'alamat_rumah','huraian_alamat','rumah_lat','rumah_long',
                'tarikh_bebas','mentor_id','kategori_prospek','jenis_penamatan',
                'nama_waris_1','no_tel_waris_1','nama_waris_2','no_tel_waris_2',
                'created_at','updated_at',
            ]);

        $data = $mentees->map(function ($m) {
            $arr = $m->toArray();
            $arr['user']['profile_photo_path'] = $m->user->profile_photo_path ?? null;
            return $arr;
        });

        return response()->json(['data' => $data]);
    }

    protected function resolveModelFromTable(string $table): ?string
    {
        $this->logCtx('resolveModelFromTable: start', ['table' => $table]);

        $explicit = DB::table('model_mappings')->where('key', $table)->value('model_class');
        if ($explicit && class_exists($explicit)) {
            $this->logCtx('resolveModelFromTable: using explicit', ['modelClass' => $explicit]);
            return $explicit;
        }

        $hardMap = [
            'lapordiri'  => \App\Models\LaporDiri::class,
            'lapor-diri' => \App\Models\LaporDiri::class,
        ];
        if (isset($hardMap[$table]) && class_exists($hardMap[$table])) {
            $this->logCtx('resolveModelFromTable: using hardMap', ['modelClass' => $hardMap[$table]]);
            return $hardMap[$table];
        }

        $normalized = str_replace(['-', ' '], '_', strtolower($table));
        $studly     = Str::studly(Str::singular($normalized));
        $class      = "App\\Models\\{$studly}";

        $this->logCtx('resolveModelFromTable: dynamic guess', [
            'normalized' => $normalized,
            'studly'     => $studly,
            'guessed'    => $class,
            'exists'     => class_exists($class),
        ]);

        return class_exists($class) ? $class : null;
    }

}
