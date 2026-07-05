<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table = 'audit_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tenant_id',
        'user_id',
        'action',
        'module',
        'entity_table',
        'entity_id',
        'before_json',
        'after_json',
        'ip_address',
        'user_agent',
        'created_at',
    ];
    protected $useTimestamps = false;
}
