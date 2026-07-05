<?php

namespace App\Models;

use CodeIgniter\Model;

class PersonnelCompetencyModel extends Model
{
    protected $table = 'personnel_competencies';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'personnel_id',
        'standard_id',
        'iaf_code_id',
        'food_chain_category_id',
        'medical_device_category_id',
        'competency_type',
        'valid_from',
        'valid_until',
        'approval_status',
        'evidence_notes',
    ];
    protected $useTimestamps = false;
}
