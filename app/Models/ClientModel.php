<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientModel extends Model
{
    protected $table = 'clients';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'tenant_id',
        'legacy_import_batch_id',
        'company',
        'legal_name',
        'address',
        'country',
        'city',
        'contact_person',
        'designation',
        'email',
        'phone',
        'website',
        'scope',
        'employee_count',
        'permanent_employees',
        'temporary_employees',
        'shift_pattern',
        'seasonal_operations',
        'number_of_sites',
        'certification_status',
        'risk_category',
        'certificate_number',
        'initial_certification_date',
        'certificate_issue_date',
        'certificate_expiry_date',
        'notes',
        'is_legacy',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
}
