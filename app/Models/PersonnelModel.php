<?php

namespace App\Models;

use CodeIgniter\Model;

class PersonnelModel extends Model
{
    protected $table = 'personnel';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'tenant_id',
        'user_id',
        'client_id',
        'full_name',
        'email',
        'phone',
        'personnel_type',
        'approval_status',
        'languages',
        'countries',
        'experience_summary',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
}
