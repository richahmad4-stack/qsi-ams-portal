<?php

namespace App\Models;

use CodeIgniter\Model;

class ReportDraftModel extends Model
{
    protected $table = 'report_drafts';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tenant_id',
        'audit_event_id',
        'report_type',
        'version_number',
        'status',
        'generated_payload',
        'editable_payload',
        'prepared_by',
        'approved_by',
        'approved_at',
        'submitted_at',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
