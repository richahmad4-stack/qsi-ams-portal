<?php

namespace App\Models;

use CodeIgniter\Model;

class TechnicalReviewModel extends Model
{
    protected $table = 'technical_reviews';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tenant_id',
        'audit_event_id',
        'reviewer_personnel_id',
        'checklist_payload',
        'competency_confirmed',
        'duration_confirmed',
        'application_confirmed',
        'reports_confirmed',
        'ncr_capa_confirmed',
        'scope_dates_confirmed',
        'impartiality_confirmed',
        'recommendation',
        'status',
        'reviewed_at',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
