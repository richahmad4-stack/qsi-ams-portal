<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientStandardModel extends Model
{
    protected $table = 'client_standards';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'client_id',
        'standard_id',
        'iaf_code_id',
        'nace_code_id',
        'food_chain_category_id',
        'medical_device_category_id',
        'scope',
    ];
    protected $useTimestamps = false;
}
