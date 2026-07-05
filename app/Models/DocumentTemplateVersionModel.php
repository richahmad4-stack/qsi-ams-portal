<?php

namespace App\Models;

use CodeIgniter\Model;

class DocumentTemplateVersionModel extends Model
{
    protected $table = 'document_template_versions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'document_template_id',
        'version_number',
        'body_html',
        'header_html',
        'footer_html',
        'created_by',
        'approved_by',
        'approved_at',
    ];
}
