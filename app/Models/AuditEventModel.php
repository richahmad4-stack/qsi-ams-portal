<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditEventModel extends Model
{
    protected $table = 'audit_events';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'audit_program_id',
        'event_type',
        'audit_number',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'audit_window_start',
        'audit_window_end',
        'duration_days',
        'status',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
