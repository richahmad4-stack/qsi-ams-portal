<?php

namespace App\Models;

use CodeIgniter\Model;

class NcrModel extends Model
{
    protected $table = 'ncrs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tenant_id',
        'audit_event_id',
        'clause_library_id',
        'ncr_number',
        'requirement',
        'finding',
        'objective_evidence',
        'classification',
        'correction',
        'root_cause',
        'corrective_action',
        'responsible_person',
        'target_date',
        'verification',
        'closure_notes',
        'status',
        'closed_at',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
