<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Throwable;

class AuditService
{
    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $action,
        ?User $user = null,
        string|Model|null $entity = null,
        array $old = [],
        array $new = [],
        array $metadata = [],
        ?Request $request = null,
    ): void {
        try {
            $entityType = null;
            $entityId = null;

            if ($entity instanceof Model) {
                $entityType = $entity::class;
                $entityId = (string) $entity->getKey();
            } elseif (is_string($entity)) {
                $entityId = $entity;
            }

            AuditLog::create([
                'user_id' => $user?->getKey(),
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $old ?: null,
                'new_values' => $new ?: null,
                'metadata' => $metadata ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Auditoria nunca deve derrubar a operação principal.
        }
    }
}
