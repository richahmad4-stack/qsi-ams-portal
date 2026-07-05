<?php

namespace App\Models;

use CodeIgniter\Model;

class ContractModel extends Model
{
    protected $table = 'contracts';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tenant_id',
        'client_id',
        'proposal_id',
        'contract_number',
        'document_number',
        'revision_number',
        'issue_number',
        'document_date',
        'version_number',
        'status',
        'signed_at',
        'signed_by_name',
        'contract_payload',
        'qsi_signatory_name',
        'qsi_signatory_date',
        'client_signatory_name',
        'client_signatory_date',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
