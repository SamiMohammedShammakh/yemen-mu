<?php

namespace App\Support;

use App\Models\AuditLog;

class Audit
{
    public static function log(string $action, $entity = null, ?array $old = null, ?array $new = null, ?int $planId = null): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'plan_id' => $planId ?? ($entity instanceof \App\Models\Plan ? $entity->id : ($entity->plan_id ?? null)),
            'old_values' => $old,
            'new_values' => $new,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
