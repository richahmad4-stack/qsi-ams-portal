<?php

namespace App\Models;

use CodeIgniter\Model;

class IafCodeModel extends Model
{
    protected $table = 'iaf_codes';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'code',
        'title',
        'risk_level',
        'active',
    ];
    protected $useTimestamps = false;
}
