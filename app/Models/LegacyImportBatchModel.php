<?php

namespace App\Models;

use CodeIgniter\Model;

class LegacyImportBatchModel extends Model
{
    protected $table = 'legacy_import_batches';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tenant_id',
        'source_type',
        'original_filename',
        'column_mapping',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'duplicate_rows',
        'status',
        'imported_by',
        'imported_at',
    ];
    protected $useTimestamps = false;
}
