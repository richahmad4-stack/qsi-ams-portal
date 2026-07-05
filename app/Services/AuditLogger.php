<?php

namespace App\Services;

use App\Models\AuditLogModel;
use CodeIgniter\HTTP\IncomingRequest;

class AuditLogger
{
    public function record(
        string $action,
        string $module,
        ?string $entityTable = null,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?int $tenantId = null,
        ?int $userId = null
    ): void {
        $request = service('request');
        $session = service('session');

        if ($tenantId === null && $session->has('tenant_id')) {
            $tenantId = (int) $session->get('tenant_id');
        }

        if ($userId === null && $session->has('user_id')) {
            $userId = (int) $session->get('user_id');
        }

        $ipAddress = null;
        $userAgent = null;

        if ($request instanceof IncomingRequest) {
            $ipAddress = $request->getIPAddress();
            $userAgent = substr((string) $request->getUserAgent(), 0, 500);
        }

        (new AuditLogModel())->insert([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'action'       => $action,
            'module'       => $module,
            'entity_table' => $entityTable,
            'entity_id'    => $entityId,
            'before_json'  => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_json'   => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
            'ip_address'   => $ipAddress,
            'user_agent'   => $userAgent,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }
}
