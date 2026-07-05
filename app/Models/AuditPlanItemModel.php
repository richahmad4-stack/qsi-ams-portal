<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditPlanItemModel extends Model
{
    protected $table = 'audit_plan_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'audit_plan_id',
        'audit_date',
        'start_time',
        'end_time',
        'activity_type',
        'department',
        'process_name',
        'clauses',
        'auditor_personnel_id',
        'notes',
        'sort_order',
    ];
}
