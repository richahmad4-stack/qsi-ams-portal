<?php

namespace App\Models;

use CodeIgniter\Model;

class StandardModel extends Model
{
    protected $table = 'standards';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'code',
        'name',
        'version',
        'scheme_type',
        'active',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
