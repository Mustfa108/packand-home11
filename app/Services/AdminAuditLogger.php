<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use Illuminate\Http\Request;

class AdminAuditLogger
{
    public static function record(
        ?int $adminId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $details = null,
        ?Request $request = null,
    ): void {
        AdminAuditLog::create([
            'admin_id'    => $adminId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'details'     => $details,
            'ip_address'  => $request?->ip(),
            'created_at'  => now(),
        ]);
    }
}
