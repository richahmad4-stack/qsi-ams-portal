<?php

namespace App\Models;

use CodeIgniter\Model;

class IntegratedAuditRequirementModel extends Model
{
    protected $table = 'integrated_audit_requirements';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tenant_id', 'requirement_code', 'title', 'audit_question', 'evidence_guidance',
        'requirement_family', 'stage_applicability', 'source_type', 'version_no', 'active',
        'created_by', 'approved_by', 'approved_at',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
