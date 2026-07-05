<?php

namespace App\Models;

use CodeIgniter\Model;

class ClauseLibraryModel extends Model
{
    protected $table = 'clause_library';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tenant_id',
        'standard_id',
        'clause_number',
        'clause_title',
        'requirement',
        'predefined_conformity_note',
        'positive_finding',
        'opportunity_for_improvement',
        'minor_nc',
        'major_nc',
        'evidence_examples',
        'auditor_guidance',
        'risk_rating',
        'stage_applicability',
        'active',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
