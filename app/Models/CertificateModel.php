<?php

namespace App\Models;

use CodeIgniter\Model;

class CertificateModel extends Model
{
    protected $table = 'certificates';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tenant_id',
        'client_id',
        'certification_decision_id',
        'certificate_number',
        'standard_id',
        'scope',
        'issue_date',
        'expiry_date',
        'initial_certification_date',
        'status',
        'qr_payload',
        'public_slug',
        'suspended_at',
        'withdrawn_at',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
