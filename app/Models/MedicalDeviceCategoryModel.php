<?php

namespace App\Models;

use CodeIgniter\Model;

class MedicalDeviceCategoryModel extends Model
{
    protected $table = 'medical_device_categories';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'code',
        'title',
        'description',
        'active',
    ];
    protected $useTimestamps = false;
}
