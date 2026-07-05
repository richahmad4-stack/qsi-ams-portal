<?php

namespace App\Models;

use CodeIgniter\Model;

class ApplicationAnswerModel extends Model
{
    protected $table = 'application_answers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'application_id',
        'application_question_id',
        'question_library_id',
        'answer_text',
        'answered_by',
        'answered_at',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
