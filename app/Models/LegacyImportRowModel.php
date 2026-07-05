<?php

namespace App\Models;

use CodeIgniter\Model;

class LegacyImportRowModel extends Model
{
    protected $table = 'legacy_import_rows';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'batch_id',
        'row_number',
        'raw_payload',
        'normalized_payload',
        'validation_errors',
        'duplicate_key',
        'status',
        'client_id',
    ];
    protected $useTimestamps = false;
}
