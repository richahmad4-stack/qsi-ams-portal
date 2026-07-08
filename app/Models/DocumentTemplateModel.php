<?php

namespace App\Models;

use CodeIgniter\Model;

class DocumentTemplateModel extends Model
{
    protected $table = 'document_templates';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tenant_id',
        'template_key',
        'name',
        'document_type',
        'document_number',
        'revision_number',
        'issue_number',
        'document_date',
        'active_version',
        'allowed_placeholders',
        'status',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
