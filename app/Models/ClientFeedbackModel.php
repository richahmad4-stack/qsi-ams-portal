<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientFeedbackModel extends Model
{
    protected $table = 'client_feedback';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tenant_id',
        'client_id',
        'audit_program_id',
        'certificate_id',
        'contact_name',
        'contact_email',
        'submitted_at',
        'overall_rating',
        'communication_rating',
        'auditor_rating',
        'report_quality_rating',
        'comments',
        'improvement_suggestion',
        'status',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
