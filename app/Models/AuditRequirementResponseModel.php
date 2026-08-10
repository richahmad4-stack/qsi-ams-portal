<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditRequirementResponseModel extends Model
{
    protected $table = 'audit_requirement_responses';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'report_draft_id', 'audit_requirement_id', 'response_text', 'objective_evidence',
        'finding_type', 'source_type', 'auditor_confirmed', 'confirmed_by_user_id',
        'confirmed_at', 'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
