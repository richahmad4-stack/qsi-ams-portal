<?php

namespace App\Models;

use CodeIgniter\Model;

class NaceCodeModel extends Model
{
    protected $table = 'nace_codes';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'code',
        'title',
        'active',
    ];
    protected $useTimestamps = false;
}
