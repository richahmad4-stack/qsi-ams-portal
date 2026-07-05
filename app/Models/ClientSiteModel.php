<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientSiteModel extends Model
{
    protected $table = 'client_sites';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'client_id',
        'site_name',
        'address',
        'country',
        'city',
        'employee_count',
        'processes',
        'active',
    ];
    protected $useTimestamps = false;
}
