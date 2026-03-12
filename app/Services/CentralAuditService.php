<?php

namespace App\Services;

use App\Models\Central\CentralAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class CentralAuditService
{
    /**
     * Log a central governance action
     */
    public function log(string $action, ?string $entityType = null, ?int $entityId = null, array $metadata = []): void
    {
        CentralAudit::create([
            'user_id' => Auth::guard('central')->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
