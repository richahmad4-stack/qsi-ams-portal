<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditProgramModel extends Model
{
    protected $table = 'audit_programs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tenant_id',
        'client_id',
        'contract_id',
        'program_number',
        'document_number',
        'revision_number',
        'issue_number',
        'document_date',
        'cycle_type',
        'certificate_issue_date',
        'surveillance_1_due_date',
        'surveillance_2_due_date',
        'certificate_expiry_date',
        'surveillance_1_status',
        'surveillance_2_status',
        'status',
        'program_payload',
        'prepared_by_name',
        'prepared_date',
        'approved_by_name',
        'approved_date',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
