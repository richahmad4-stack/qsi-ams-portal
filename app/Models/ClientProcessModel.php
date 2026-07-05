<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientProcessModel extends Model
{
    protected $table = 'client_processes';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'client_id',
        'process_name',
        'description',
    ];
    protected $useTimestamps = false;
}
