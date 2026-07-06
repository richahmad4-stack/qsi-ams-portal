<?php

namespace App\Services;

use Config\Database;

class DashboardService
{
    private const GLOBAL_DASHBOARD_ROLES = [
        'super_admin',
        'administrator',
        'certification_manager',
        'technical_manager',
        'quality_manager',
        'general_manager',
        'chief_operating_officer',
    ];

    public function metrics(int $tenantId): array
    {
        $today = date('Y-m-d');
        $next30 = date('Y-m-d', strtotime('+30 days'));
        $next90 = date('Y-m-d', strtotime('+90 days'));
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        return [
            'cards' => [
                'total_clients' => $this->count('clients', ['tenant_id' => $tenantId]),
                'active_clients' => $this->countClientsByStatus($tenantId, ['certified', 'active']),
                'active_certificates' => $this->count('certificates', ['tenant_id' => $tenantId, 'status' => 'active']),
                'expired_certificates' => $this->expiredCertificates($tenantId, $today),
                'suspended_certificates' => $this->count('certificates', ['tenant_id' => $tenantId, 'status' => 'suspended']),
                'withdrawn_certificates' => $this->count('certificates', ['tenant_id' => $tenantId, 'status' => 'withdrawn']),
                'pending_applications' => $this->pendingApplications($tenantId),
                'certificates_expiring' => $this->certificatesExpiring($tenantId, $today, $next90),
                'open_ncrs' => $this->openCount('ncrs', $tenantId),
                'open_capas' => $this->openCount('capas', $tenantId),
                'closed_capas' => $this->closedCapas($tenantId),
                'completed_audits' => $this->completedAudits($tenantId),
                'pending_technical_reviews' => $this->count('technical_reviews', ['tenant_id' => $tenantId, 'status' => 'pending']),
                'pending_certification_decisions' => $this->count('certification_decisions', ['tenant_id' => $tenantId, 'status' => 'pending']),
                'upcoming_audits' => $this->upcomingAudits($tenantId, $today, $next30),
                'upcoming_surveillance_audits' => $this->upcomingSurveillanceAudits($tenantId, $today, $next90),
                'customer_feedback' => $this->feedbackCount($tenantId),
            ],
            'proposal_pipeline' => $this->proposalPipeline($tenantId),
            'fee_summary' => $this->feeSummary($tenantId),
            'clients_by_status' => $this->clientsByStatus($tenantId),
            'certificates_by_standard' => $this->certificatesByStandard($tenantId),
            'audits_by_month' => $this->auditsByMonth($tenantId, date('Y-m-01', strtotime('-5 months')), $monthEnd),
            'open_ncrs_by_severity' => $this->openNcrsBySeverity($tenantId),
            'capa_status' => $this->capaStatus($tenantId),
            'auditor_workload' => $this->auditorWorkload($tenantId),
            'audit_calendar' => $this->auditCalendar($tenantId, $today, $next90),
            'audit_status' => $this->auditStatus($tenantId),
            'upcoming_surveillance_table' => $this->auditCalendar($tenantId, $today, $next90, ['surveillance1', 'surveillance2']),
            'expiring_certificates' => $this->expiringCertificateRows($tenantId, $today, $next90),
            'recent_activities' => $this->recentActivities($tenantId),
        ];
    }

    public function dashboardForUser(int $tenantId, int $userId, array $roles): array
    {
        if (array_intersect(self::GLOBAL_DASHBOARD_ROLES, $roles) !== []) {
            return [
                'mode' => 'global',
                'data' => $this->metrics($tenantId),
            ];
        }

        $personnelId = $this->personnelIdForUser($tenantId, $userId);

        return [
            'mode' => 'personal',
            'data' => [
                'cards' => [
                    'my_open_audits' => $personnelId === null ? 0 : $this->assignedAuditCount($tenantId, $personnelId, false),
                    'my_closed_audits' => $personnelId === null ? 0 : $this->assignedAuditCount($tenantId, $personnelId, true),
                    'my_due_30' => $personnelId === null ? 0 : $this->assignedAuditDueCount($tenantId, $personnelId),
                    'my_open_ncrs' => $personnelId === null ? 0 : $this->assignedNcrCount($tenantId, $personnelId),
                    'my_reviews' => $personnelId === null ? 0 : $this->assignedTechnicalReviewCount($tenantId, $personnelId),
                    'my_decisions' => $personnelId === null ? 0 : $this->assignedDecisionCount($tenantId, $personnelId),
                    'finance_items' => in_array('finance', $roles, true) ? $this->financeWorkCount($tenantId) : 0,
                ],
                'assigned_audits' => $personnelId === null ? [] : $this->assignedAudits($tenantId, $personnelId),
                'assigned_reviews' => $personnelId === null ? [] : $this->assignedTechnicalReviews($tenantId, $personnelId),
                'assigned_decisions' => $personnelId === null ? [] : $this->assignedDecisions($tenantId, $personnelId),
                'finance_summary' => in_array('finance', $roles, true) ? $this->feeSummary($tenantId) : [],
            ],
        ];
    }

    private function db()
    {
        return Database::connect();
    }

    private function count(string $table, array $where): int
    {
        $builder = $this->db()->table($table);

        foreach ($where as $column => $value) {
            $builder->where($column, $value);
        }

        if ($this->hasSoftDelete($table)) {
            $builder->where('deleted_at', null);
        }

        return (int) $builder->countAllResults();
    }

    private function openCount(string $table, int $tenantId): int
    {
        return (int) $this->db()->table($table)
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['closed', 'cancelled', 'rejected'])
            ->countAllResults();
    }

    private function countClientsByStatus(int $tenantId, array $statuses): int
    {
        return (int) $this->db()->table('clients')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->whereIn('certification_status', $statuses)
            ->countAllResults();
    }

    private function pendingApplications(int $tenantId): int
    {
        return (int) $this->db()->table('certification_applications')
            ->join('clients', 'clients.id = certification_applications.client_id')
            ->where('certification_applications.tenant_id', $tenantId)
            ->whereNotIn('certification_applications.status', ['approved', 'rejected', 'withdrawn'])
            ->where('clients.deleted_at', null)
            ->countAllResults();
    }

    private function closedCapas(int $tenantId): int
    {
        return (int) $this->db()->table('capas')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['closed', 'verified_closed'])
            ->countAllResults();
    }

    private function completedAudits(int $tenantId): int
    {
        return (int) $this->db()->table('audit_events')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->where('audit_programs.tenant_id', $tenantId)
            ->whereIn('audit_events.status', ['completed', 'closed'])
            ->countAllResults();
    }

    private function feedbackCount(int $tenantId): int
    {
        return (int) $this->db()->table('client_feedback')
            ->where('tenant_id', $tenantId)
            ->countAllResults();
    }

    private function expiredCertificates(int $tenantId, string $today): int
    {
        return (int) $this->db()->table('certificates')
            ->where('tenant_id', $tenantId)
            ->where('expiry_date <', $today)
            ->where('status', 'active')
            ->countAllResults();
    }

    private function certificatesExpiring(int $tenantId, string $today, string $next90): int
    {
        return (int) $this->db()->table('certificates')
            ->where('tenant_id', $tenantId)
            ->where('expiry_date >=', $today)
            ->where('expiry_date <=', $next90)
            ->where('status', 'active')
            ->countAllResults();
    }

    private function upcomingAudits(int $tenantId, string $today, string $next30): int
    {
        return (int) $this->db()->table('audit_events')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->where('audit_programs.tenant_id', $tenantId)
            ->where('audit_events.planned_start_date >=', $today)
            ->where('audit_events.planned_start_date <=', $next30)
            ->whereIn('audit_events.status', ['planned', 'scheduled'])
            ->countAllResults();
    }

    private function upcomingSurveillanceAudits(int $tenantId, string $today, string $next90): int
    {
        return (int) $this->db()->table('audit_events')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->where('audit_programs.tenant_id', $tenantId)
            ->where('audit_events.planned_start_date >=', $today)
            ->where('audit_events.planned_start_date <=', $next90)
            ->whereIn('audit_events.event_type', ['surveillance1', 'surveillance2'])
            ->whereIn('audit_events.status', ['planned', 'scheduled'])
            ->countAllResults();
    }

    private function revenue(int $tenantId, ?string $from, ?string $to): float
    {
        $builder = $this->db()->table('payments')
            ->select('COALESCE(SUM(payments.amount), 0) AS total', false)
            ->join('invoices', 'invoices.id = payments.invoice_id')
            ->where('invoices.tenant_id', $tenantId);

        if ($from !== null) {
            $builder->where('payments.payment_date >=', $from);
        }

        if ($to !== null) {
            $builder->where('payments.payment_date <=', $to);
        }

        return (float) ($builder->get()->getRowArray()['total'] ?? 0);
    }

    private function proposalPipeline(int $tenantId): array
    {
        return $this->db()->table('proposals')
            ->select('status, COUNT(*) AS total')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->groupBy('status')
            ->orderBy('status', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function feeSummary(int $tenantId): array
    {
        $row = $this->db()->table('proposals')
            ->select('
                COALESCE(SUM(certification_fee), 0) AS certification_fee,
                COALESCE(SUM(surveillance1_fee), 0) AS surveillance1_fee,
                COALESCE(SUM(surveillance2_fee), 0) AS surveillance2_fee
            ', false)
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return [
            'certification_fee' => (float) ($row['certification_fee'] ?? 0),
            'surveillance1_fee' => (float) ($row['surveillance1_fee'] ?? 0),
            'surveillance2_fee' => (float) ($row['surveillance2_fee'] ?? 0),
        ];
    }

    private function auditorWorkload(int $tenantId): array
    {
        return $this->db()->table('auditor_appointments')
            ->select('personnel.full_name, COUNT(*) AS total')
            ->join('personnel', 'personnel.id = auditor_appointments.personnel_id')
            ->join('audit_events', 'audit_events.id = auditor_appointments.audit_event_id')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->where('audit_programs.tenant_id', $tenantId)
            ->whereIn('auditor_appointments.appointment_role', ['auditor', 'lead_auditor'])
            ->whereIn('audit_events.status', ['planned', 'scheduled', 'in_progress'])
            ->groupBy('personnel.id, personnel.full_name')
            ->orderBy('total', 'DESC')
            ->limit(8)
            ->get()
            ->getResultArray();
    }

    private function auditCalendar(int $tenantId, string $today, string $next90, array $types = []): array
    {
        $builder = $this->db()->table('audit_events')
            ->select('audit_events.audit_number, audit_events.event_type, audit_events.planned_start_date, audit_events.planned_end_date, audit_events.status, clients.company')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->join('clients', 'clients.id = audit_programs.client_id')
            ->where('audit_programs.tenant_id', $tenantId)
            ->where('audit_events.planned_start_date >=', $today)
            ->where('audit_events.planned_start_date <=', $next90);

        if ($types !== []) {
            $builder->whereIn('audit_events.event_type', $types);
        }

        return $builder
            ->orderBy('audit_events.planned_start_date', 'ASC')
            ->limit(10)
            ->get()
            ->getResultArray();
    }

    private function auditStatus(int $tenantId): array
    {
        return $this->db()->table('audit_events')
            ->select('audit_events.status, COUNT(*) AS total')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->where('audit_programs.tenant_id', $tenantId)
            ->groupBy('audit_events.status')
            ->orderBy('audit_events.status', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function clientsByStatus(int $tenantId): array
    {
        return $this->db()->table('clients')
            ->select('certification_status AS status, COUNT(*) AS total')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->groupBy('certification_status')
            ->orderBy('certification_status', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function certificatesByStandard(int $tenantId): array
    {
        return $this->db()->table('certificates')
            ->select('standards.code AS standard_code, COUNT(*) AS total')
            ->join('standards', 'standards.id = certificates.standard_id')
            ->where('certificates.tenant_id', $tenantId)
            ->groupBy('standards.code')
            ->orderBy('standards.code', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function auditsByMonth(int $tenantId, string $from, string $to): array
    {
        return $this->db()->table('audit_events')
            ->select("DATE_FORMAT(audit_events.planned_start_date, '%Y-%m') AS month, COUNT(*) AS total", false)
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->where('audit_programs.tenant_id', $tenantId)
            ->where('audit_events.planned_start_date >=', $from)
            ->where('audit_events.planned_start_date <=', $to)
            ->groupBy("DATE_FORMAT(audit_events.planned_start_date, '%Y-%m')", false)
            ->orderBy('month', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function openNcrsBySeverity(int $tenantId): array
    {
        return $this->db()->table('ncrs')
            ->select('classification AS severity, COUNT(*) AS total')
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['closed', 'verified_closed', 'cancelled'])
            ->groupBy('classification')
            ->orderBy('classification', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function capaStatus(int $tenantId): array
    {
        return $this->db()->table('capas')
            ->select('status, COUNT(*) AS total')
            ->where('tenant_id', $tenantId)
            ->groupBy('status')
            ->orderBy('status', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function expiringCertificateRows(int $tenantId, string $today, string $next90): array
    {
        return $this->db()->table('certificates')
            ->select('certificates.certificate_number, certificates.expiry_date, certificates.status, clients.company, standards.code AS standard_code')
            ->join('clients', 'clients.id = certificates.client_id')
            ->join('standards', 'standards.id = certificates.standard_id')
            ->where('certificates.tenant_id', $tenantId)
            ->where('certificates.expiry_date >=', $today)
            ->where('certificates.expiry_date <=', $next90)
            ->where('certificates.status', 'active')
            ->orderBy('certificates.expiry_date', 'ASC')
            ->limit(10)
            ->get()
            ->getResultArray();
    }

    private function recentActivities(int $tenantId): array
    {
        return $this->db()->table('audit_logs')
            ->select('audit_logs.action, audit_logs.module, audit_logs.entity_table, audit_logs.entity_id, audit_logs.created_at, users.full_name')
            ->join('users', 'users.id = audit_logs.user_id', 'left')
            ->where('audit_logs.tenant_id', $tenantId)
            ->orderBy('audit_logs.created_at', 'DESC')
            ->limit(12)
            ->get()
            ->getResultArray();
    }

    private function personnelIdForUser(int $tenantId, int $userId): ?int
    {
        $row = $this->db()->table('personnel')
            ->select('id')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    private function assignedAuditCount(int $tenantId, int $personnelId, bool $closed): int
    {
        $builder = $this->assignedAuditBase($tenantId, $personnelId);

        if ($closed) {
            $builder->whereIn('audit_events.status', ['completed', 'closed']);
        } else {
            $builder->whereNotIn('audit_events.status', ['completed', 'closed', 'cancelled']);
        }

        return (int) $builder->select('audit_events.id')->distinct()->countAllResults();
    }

    private function assignedAuditDueCount(int $tenantId, int $personnelId): int
    {
        return (int) $this->assignedAuditBase($tenantId, $personnelId)
            ->select('audit_events.id')
            ->distinct()
            ->where('audit_events.planned_start_date >=', date('Y-m-d'))
            ->where('audit_events.planned_start_date <=', date('Y-m-d', strtotime('+30 days')))
            ->whereNotIn('audit_events.status', ['completed', 'closed', 'cancelled'])
            ->countAllResults();
    }

    private function assignedNcrCount(int $tenantId, int $personnelId): int
    {
        return (int) $this->db()->table('ncrs')
            ->join('audit_events', 'audit_events.id = ncrs.audit_event_id')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->join('auditor_appointments', 'auditor_appointments.audit_event_id = audit_events.id')
            ->where('audit_programs.tenant_id', $tenantId)
            ->where('auditor_appointments.personnel_id', $personnelId)
            ->whereIn('auditor_appointments.status', ['appointed', 'accepted', 'confirmed', 'approved', 'active'])
            ->whereNotIn('ncrs.status', ['closed', 'verified_closed', 'cancelled'])
            ->select('ncrs.id')
            ->distinct()
            ->countAllResults();
    }

    private function assignedTechnicalReviewCount(int $tenantId, int $personnelId): int
    {
        return (int) $this->db()->table('technical_reviews')
            ->where('tenant_id', $tenantId)
            ->where('reviewer_personnel_id', $personnelId)
            ->whereNotIn('status', ['approved', 'closed', 'completed', 'cancelled'])
            ->countAllResults();
    }

    private function assignedDecisionCount(int $tenantId, int $personnelId): int
    {
        return (int) $this->db()->table('certification_decisions')
            ->where('tenant_id', $tenantId)
            ->where('decision_maker_personnel_id', $personnelId)
            ->whereNotIn('status', ['approved', 'closed', 'completed', 'cancelled'])
            ->countAllResults();
    }

    private function financeWorkCount(int $tenantId): int
    {
        return (int) $this->db()->table('proposals')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->whereIn('status', ['accepted', 'contracted'])
            ->countAllResults();
    }

    private function assignedAudits(int $tenantId, int $personnelId): array
    {
        return $this->assignedAuditBase($tenantId, $personnelId)
            ->select("audit_events.id, audit_events.audit_program_id, audit_events.event_type, audit_events.audit_number, audit_events.planned_start_date, audit_events.planned_end_date, audit_events.actual_start_date, audit_events.actual_end_date, audit_events.audit_window_start, audit_events.audit_window_end, audit_events.duration_days, audit_events.status, audit_events.created_at, audit_events.updated_at, audit_programs.client_id, clients.company, GROUP_CONCAT(DISTINCT auditor_appointments.appointment_role ORDER BY auditor_appointments.appointment_role SEPARATOR ', ') AS appointment_role", false)
            ->groupBy('audit_events.id, audit_events.audit_program_id, audit_events.event_type, audit_events.audit_number, audit_events.planned_start_date, audit_events.planned_end_date, audit_events.actual_start_date, audit_events.actual_end_date, audit_events.audit_window_start, audit_events.audit_window_end, audit_events.duration_days, audit_events.status, audit_events.created_at, audit_events.updated_at, audit_programs.client_id, clients.company')
            ->orderBy('audit_events.planned_start_date', 'DESC')
            ->limit(25)
            ->get()
            ->getResultArray();
    }

    private function assignedTechnicalReviews(int $tenantId, int $personnelId): array
    {
        return $this->db()->table('technical_reviews')
            ->select('technical_reviews.*, clients.id AS client_id, clients.company, audit_events.event_type, audit_events.audit_number')
            ->join('audit_events', 'audit_events.id = technical_reviews.audit_event_id')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->join('clients', 'clients.id = audit_programs.client_id')
            ->where('technical_reviews.tenant_id', $tenantId)
            ->where('technical_reviews.reviewer_personnel_id', $personnelId)
            ->orderBy('technical_reviews.id', 'DESC')
            ->limit(15)
            ->get()
            ->getResultArray();
    }

    private function assignedDecisions(int $tenantId, int $personnelId): array
    {
        return $this->db()->table('certification_decisions')
            ->select('certification_decisions.*, technical_reviews.audit_event_id, clients.id AS client_id, clients.company, audit_events.event_type, audit_events.audit_number')
            ->join('technical_reviews', 'technical_reviews.id = certification_decisions.technical_review_id')
            ->join('audit_events', 'audit_events.id = technical_reviews.audit_event_id')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->join('clients', 'clients.id = audit_programs.client_id')
            ->where('certification_decisions.tenant_id', $tenantId)
            ->where('certification_decisions.decision_maker_personnel_id', $personnelId)
            ->orderBy('certification_decisions.id', 'DESC')
            ->limit(15)
            ->get()
            ->getResultArray();
    }

    private function assignedAuditBase(int $tenantId, int $personnelId)
    {
        return $this->db()->table('audit_events')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->join('clients', 'clients.id = audit_programs.client_id')
            ->join('auditor_appointments', 'auditor_appointments.audit_event_id = audit_events.id')
            ->where('audit_programs.tenant_id', $tenantId)
            ->where('auditor_appointments.personnel_id', $personnelId)
            ->whereIn('auditor_appointments.status', ['appointed', 'accepted', 'confirmed', 'approved', 'active']);
    }

    private function hasSoftDelete(string $table): bool
    {
        return in_array($table, ['clients', 'proposals', 'roles', 'users', 'personnel'], true);
    }
}
