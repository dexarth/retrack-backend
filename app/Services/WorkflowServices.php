<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WorkflowServices
{
    public static function validate(string $model, array $data, string $action = 'create')
    {
        $rules = DB::table('workflow_rules')
            ->where('model', $model)
            ->where('trigger', 'before_' . $action)
            ->get();

        foreach ($rules as $rule) {
            $field = $rule->field;
            $type = $rule->type;
            $message = $rule->message ?? 'Tindakan ini tidak dibenarkan.';
            $conditions = json_decode($rule->conditions, true);

            if ($type === 'unique_per_day') {
                $dateColumn = $conditions['date_column'] ?? 'created_at';
                $exists = DB::table($model)
                    ->where($field, $data[$field])
                    ->whereDate($dateColumn, Carbon::today())
                    ->exists();

                if ($exists) return $message;
            }

            // Extendable: add more rule types
        }

        return null;
    }

    public static function trigger(string $model, $user, string $action = 'after_get')
    {
        $rules = DB::table('workflow_rules')
            ->where('model', $model)
            ->where('trigger', $action)
            ->get();

        foreach ($rules as $rule) {
            $type = $rule->type;
            $field = $rule->field;
            $conditions = json_decode($rule->conditions, true);

            if ($type === 'reminder_if_no_submission') {
                $dateColumn = $conditions['date_column'] ?? 'created_at';
                $thresholdHours = $conditions['threshold_hours'] ?? 48;

                $last = DB::table($model)
                    ->where($field, $user->id)
                    ->orderByDesc($dateColumn)
                    ->first();

                if (!$last || Carbon::parse($last->$dateColumn)->diffInHours(now()) >= $thresholdHours) {
                    Log::info("[Workflow Reminder] User {$user->id} has no laporan in {$thresholdHours}h.");
                    // You can trigger email/notification here if needed
                }
            }

            // Extendable: add other post-get actions
        }
    }
}
