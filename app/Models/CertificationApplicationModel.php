<?php

namespace App\Models;

use CodeIgniter\Model;

class CertificationApplicationModel extends Model
{
    protected $table = 'certification_applications';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tenant_id',
        'client_id',
        'application_number',
        'document_number',
        'revision_number',
        'issue_number',
        'issue_date',
        'status',
        'submitted_at',
        'declaration_name',
        'declaration_position',
        'declaration_date',
        'cb_review_status',
        'cb_review_notes',
        'reviewed_by',
        'reviewed_at',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
