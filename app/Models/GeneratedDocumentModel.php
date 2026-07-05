<?php

namespace App\Models;

use CodeIgniter\Model;

class GeneratedDocumentModel extends Model
{
    protected $table = 'generated_documents';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tenant_id',
        'client_id',
        'document_key',
        'document_title',
        'related_table',
        'related_id',
        'storage_path',
        'mime_type',
        'generated_by',
        'generated_at',
    ];
}
