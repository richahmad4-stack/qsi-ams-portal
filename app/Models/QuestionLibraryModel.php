<?php

namespace App\Models;

use CodeIgniter\Model;

class QuestionLibraryModel extends Model
{
    protected $table = 'question_library';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'question_key',
        'question_text',
        'question_type',
        'applicable_standards',
        'mandatory',
        'section',
        'display_order',
        'validation_rules',
        'help_text',
        'default_answer',
        'active',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
