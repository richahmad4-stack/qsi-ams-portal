<?php

namespace App\Models;

use CodeIgniter\Model;

class ApplicationReviewModel extends Model
{
    protected $table = 'application_reviews';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'client_id',
        'questionnaire_response_id',
        'application_review_number',
        'certification_application_id',
        'document_number',
        'revision_number',
        'issue_number',
        'document_date',
        'technical_manager_id',
        'quality_manager_id',
        'completeness_status',
        'risk_rating',
        'recommendation',
        'md5_duration_days',
        'iso22003_duration_days',
        'integrated_reduction_percent',
        'stage1_days',
        'stage2_days',
        'review_notes',
        'review_payload',
        'status',
        'reviewed_at',
        'technical_reviewer_name',
        'technical_review_date',
        'quality_manager_status',
        'quality_manager_comments',
        'quality_manager_name',
        'quality_manager_date',
        'general_manager_status',
        'general_manager_comments',
        'general_manager_name',
        'general_manager_date',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
