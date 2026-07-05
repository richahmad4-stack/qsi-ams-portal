<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditPlanModel extends Model
{
    protected $table = 'audit_plans';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'audit_event_id',
        'plan_number',
        'version_number',
        'status',
        'prepared_by',
        'approved_by',
        'approved_at',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
