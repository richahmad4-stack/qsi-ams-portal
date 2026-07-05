<?php

namespace App\Models;

use CodeIgniter\Model;

class ApplicationSelectedStandardModel extends Model
{
    protected $table = 'application_selected_standards';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'application_id',
        'standard_id',
        'standard_code',
    ];
    protected $useTimestamps = false;
}
