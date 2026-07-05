<?php

namespace App\Models;

use CodeIgniter\Model;

class CertificationDecisionModel extends Model
{
    protected $table = 'certification_decisions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tenant_id',
        'technical_review_id',
        'decision_maker_personnel_id',
        'decision',
        'reason',
        'electronic_signature',
        'decided_at',
        'status',
        'gm_approved_by_user_id',
        'gm_approval_notes',
        'gm_approved_at',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
