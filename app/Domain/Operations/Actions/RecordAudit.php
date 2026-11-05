<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class RecordAudit
{
    /**
     * Persist an administrative action for the audit trail.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function handle(string $action, ?Model $entity = null, array $oldValues = [], array $newValues = []): AuditLog
    {
        $request = request();

        return AuditLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entity ? $entity::class : null,
            'entity_id' => $entity?->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            'created_at' => now(),
        ]);
    }
}
