<?php
declare(strict_types=1);

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            static::logAuditAction($model, 'created');
        });

        static::updated(function (Model $model) {
            static::logAuditAction($model, 'updated');
        });

        static::deleted(function (Model $model) {
            static::logAuditAction($model, 'deleted');
        });
    }

    protected static function logAuditAction(Model $model, string $action): void
    {
        $user = auth()->user() ?? auth('sanctum')->user();
        if (!$user) {
            return; // Skip if action is outside user session (e.g. queue/crawlers)
        }

        // Determine company ID
        $companyId = $model->company_id ?? $user->company_id;
        if (!$companyId) {
            return;
        }

        // Sensitive columns to exclude from logging changes
        $ignoredColumns = ['password', 'remember_token', 'two_factor_recovery_codes', 'two_factor_secret', 'raw_payload'];
        $changes = [];

        if ($action === 'created') {
            $changes = array_diff_key($model->getAttributes(), array_flip($ignoredColumns));
        } elseif ($action === 'updated') {
            $dirty = $model->getDirty();
            foreach ($dirty as $key => $newValue) {
                if (in_array($key, $ignoredColumns, true)) {
                    continue;
                }
                $changes[$key] = [
                    'old' => $model->getOriginal($key),
                    'new' => $newValue
                ];
            }
            if (empty($changes)) {
                return; // Nothing audited changed
            }
        } elseif ($action === 'deleted') {
            $changes = array_diff_key($model->getAttributes(), array_flip($ignoredColumns));
        }

        AuditLog::create([
            'user_id' => $user->id,
            'company_id' => $companyId,
            'action' => strtolower(class_basename($model)) . '.' . $action,
            'resource_type' => get_class($model),
            'resource_id' => $model->getKey(),
            'changes' => $changes,
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent() ?? 'System',
        ]);
    }
}
