<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditorAppointmentModel extends Model
{
    protected $table = 'auditor_appointments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'audit_event_id',
        'personnel_id',
        'appointment_role',
        'appointed_by',
        'appointed_at',
        'status',
        'conflict_check_json',
    ];
}
