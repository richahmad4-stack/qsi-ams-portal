<?php

namespace App\Models;

use CodeIgniter\Model;

class CapaModel extends Model
{
    protected $table = 'capas';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tenant_id',
        'ncr_id',
        'capa_number',
        'source',
        'issue',
        'immediate_correction',
        'root_cause',
        'five_why',
        'fishbone',
        'corrective_action',
        'preventive_action',
        'responsible_person',
        'target_date',
        'evidence_reference',
        'verification',
        'effectiveness',
        'closure_notes',
        'status',
        'closed_at',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
