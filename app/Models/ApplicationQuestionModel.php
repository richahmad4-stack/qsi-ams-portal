<?php

namespace App\Models;

use CodeIgniter\Model;

class ApplicationQuestionModel extends Model
{
    protected $table = 'application_questions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'application_id',
        'question_library_id',
        'question_key',
        'question_text',
        'question_type',
        'section',
        'display_order',
        'mandatory',
        'validation_rules',
        'help_text',
        'standard_codes',
    ];
    protected $useTimestamps = false;
}
