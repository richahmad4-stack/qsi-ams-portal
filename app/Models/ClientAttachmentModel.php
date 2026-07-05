<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientAttachmentModel extends Model
{
    protected $table = 'client_attachments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'client_id',
        'uploaded_by',
        'category',
        'original_filename',
        'storage_path',
        'mime_type',
        'file_size',
        'checksum_sha256',
    ];
    protected $useTimestamps = false;
}
