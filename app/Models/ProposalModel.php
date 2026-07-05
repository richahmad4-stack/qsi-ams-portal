<?php

namespace App\Models;

use CodeIgniter\Model;

class ProposalModel extends Model
{
    protected $table = 'proposals';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'tenant_id',
        'client_id',
        'application_review_id',
        'proposal_number',
        'version_number',
        'status',
        'proposal_date',
        'client_reference',
        'valid_until',
        'certification_fee',
        'surveillance1_fee',
        'surveillance2_fee',
        'training_fee',
        'travel_fee',
        'accommodation_fee',
        'discount_amount',
        'vat_percent',
        'vat_amount',
        'grand_total',
        'currency',
        'proposal_payload',
        'created_by',
        'approved_by',
        'approved_at',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
}
