<?php

namespace App\Models;

use CodeIgniter\Model;

class ReportSectionModel extends Model
{
    protected $table = 'report_sections';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'report_draft_id',
        'clause_library_id',
        'section_key',
        'section_title',
        'section_content',
        'sort_order',
    ];
}
