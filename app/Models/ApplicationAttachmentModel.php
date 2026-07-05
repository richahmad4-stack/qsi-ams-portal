<?php

namespace App\Models;

use CodeIgniter\Model;

class ApplicationAttachmentModel extends Model
{
    protected $table = 'application_attachments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'application_id',
        'application_question_id',
        'uploaded_by',
        'category',
        'original_filename',
        'storage_path',
        'mime_type',
        'file_size',
    ];
    protected $useTimestamps = false;
}
