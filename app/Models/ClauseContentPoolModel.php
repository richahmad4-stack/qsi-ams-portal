<?php

namespace App\Models;

use CodeIgniter\Model;

class ClauseContentPoolModel extends Model
{
    protected $table = 'clause_content_pool';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tenant_id',
        'standard_id',
        'clause_library_id',
        'scope_keyword',
        'industry_type',
        'iaf_code_id',
        'food_chain_category_id',
        'medical_device_category_id',
        'audit_stage',
        'content_type',
        'severity',
        'template_code',
        'template_title',
        'content_text',
        'tags',
        'active',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
