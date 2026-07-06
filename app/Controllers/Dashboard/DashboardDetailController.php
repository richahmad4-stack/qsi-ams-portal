<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use Config\Database;

class DashboardDetailController extends BaseController
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

    public function show(string $section)
    {
        $tenantId = (int) session()->get('tenant_id');
        if (! $this->canOpenSection($section)) {
            return redirect()->to('/dashboard')->with('error', 'This dashboard section is not assigned to your role.');
        }

        $definition = $this->definition($section, $tenantId);

        if ($definition === null) {
            return redirect()->to('/dashboard')->with('error', 'Dashboard section not found.');
        }

        return view('dashboard/section', [
            'title' => $definition['title'],
            'pageTitle' => $definition['title'],
            'pageSubtitle' => 'Dashboard records',
            'section' => $section,
            'columns' => $definition['columns'],
            'rows' => $definition['rows'],
        ]);
    }

    private function canOpenSection(string $section): bool
    {
        $roles = (array) session()->get('role_codes');
        if (array_intersect(self::GLOBAL_DASHBOARD_ROLES, $roles) !== []) {
            return true;
        }

        if ($section === 'my_finance_items') {
            return in_array('finance', $roles, true);
        }

        return str_starts_with($section, 'my_');
    }

    private function definition(string $section, int $tenantId): ?array
    {
        $today = date('Y-m-d');
        $next30 = date('Y-m-d', strtotime('+30 days'));
        $next90 = date('Y-m-d', strtotime('+90 days'));
        $db = Database::connect();
        $personnelId = $this->currentPersonnelId($tenantId);

        return match ($section) {
            'my_audits', 'my_open_audits' => $personnelId === null ? $this->emptySection('My open audits', ['Client', 'Audit', 'Stage', 'Role', 'Start', 'Status']) : $this->myAuditSection('My open audits', $personnelId, false),
            'my_closed_audits' => $personnelId === null ? $this->emptySection('My closed audits', ['Client', 'Audit', 'Stage', 'Role', 'Start', 'Status']) : $this->myAuditSection('My closed audits', $personnelId, true),
            'my_audits_due' => $personnelId === null ? $this->emptySection('Audits due in 30 days', ['Client', 'Audit', 'Stage', 'Role', 'Start', 'Status']) : $this->myAuditSection('Audits due in 30 days', $personnelId, false, $today, $next30),
            'my_open_ncrs' => $personnelId === null ? $this->emptySection('My open NCRs', ['NCR', 'Client', 'Audit stage', 'Class', 'Status', 'Target']) : $this->myNcrSection($personnelId),
            'my_technical_reviews' => $personnelId === null ? $this->emptySection('My technical reviews', ['Client', 'Audit stage', 'Audit', 'Recommendation', 'Status']) : $this->myTechnicalReviewSection($personnelId),
            'my_decisions' => $personnelId === null ? $this->emptySection('My decisions', ['Client', 'Audit stage', 'Audit', 'Decision', 'Status']) : $this->myDecisionSection($personnelId),
            'my_finance_items' => $this->proposalSection('My finance items'),
            'total_clients' => $this->clientSection('Total clients', $db->table('clients')->where('tenant_id', $tenantId)->where('deleted_at', null)->orderBy('company', 'ASC')->get()->getResultArray()),
            'legacy_clients' => $this->clientSection('Legacy clients', $db->table('clients')->where('tenant_id', $tenantId)->where('is_legacy', 1)->where('deleted_at', null)->orderBy('company', 'ASC')->get()->getResultArray()),
            'active_clients' => $this->clientSection('Active clients', $db->table('clients')->where('tenant_id', $tenantId)->whereIn('certification_status', ['certified', 'active'])->where('deleted_at', null)->orderBy('company', 'ASC')->get()->getResultArray()),
            'active_certificates' => $this->certificateSection('Active certificates', $this->certificates($tenantId, ['certificates.status' => 'active'])),
            'suspended_certificates' => $this->certificateSection('Suspended certificates', $this->certificates($tenantId, ['certificates.status' => 'suspended'])),
            'withdrawn_certificates' => $this->certificateSection('Withdrawn certificates', $this->certificates($tenantId, ['certificates.status' => 'withdrawn'])),
            'expired_certificates' => $this->certificateSection('Expired certificates', $this->certificates($tenantId, ['certificates.status' => 'active'], ['certificates.expiry_date <' => $today])),
            'certificates_expiring' => $this->certificateSection('Certificates expiring', $this->certificates($tenantId, ['certificates.status' => 'active'], ['certificates.expiry_date >=' => $today, 'certificates.expiry_date <=' => $next90])),
            'pending_applications' => $this->applicationSection('Pending applications'),
            'open_ncrs' => $this->ncrSection('Open NCRs', false),
            'open_capas' => $this->capaSection('Open CAPAs'),
            'closed_capas' => $this->capaSection('Closed CAPAs', true),
            'completed_audits' => $this->auditStatusSection('Completed audits', ['completed', 'closed']),
            'pending_technical_reviews' => $this->technicalReviewSection('Pending technical reviews', 'pending'),
            'pending_certification_decisions' => $this->decisionSection('Pending certification decisions', 'pending'),
            'upcoming_audits' => $this->auditSection('Upcoming audits', $today, $next30, []),
            'upcoming_surveillance_audits' => $this->auditSection('Upcoming surveillance audits', $today, $next90, ['surveillance1', 'surveillance2']),
            'customer_feedback' => $this->feedbackSection('Customer feedback summary'),
            'monthly_revenue' => $this->proposalSection('Monthly revenue source proposals'),
            default => null,
        };
    }

    private function emptySection(string $title, array $columns): array
    {
        return [
            'title' => $title,
            'columns' => $columns,
            'rows' => [],
        ];
    }

    private function clientSection(string $title, array $clients): array
    {
        return [
            'title' => $title,
            'columns' => ['Company', 'Status', 'Contact', 'Certificate expiry'],
            'rows' => array_map(fn (array $client): array => [
                'cells' => [$client['company'], str_replace('_', ' ', $client['certification_status']), $client['contact_person'] ?: $client['email'], $client['certificate_expiry_date']],
                'view' => site_url('masters/clients/' . $client['id']),
                'edit' => site_url('masters/clients/' . $client['id'] . '/edit'),
                'pdf' => site_url('workflow/certification/' . $client['id'] . '/documents/audit_report'),
            ], $clients),
        ];
    }

    private function certificateSection(string $title, array $certificates): array
    {
        return [
            'title' => $title,
            'columns' => ['Certificate', 'Client', 'Standard', 'Issue', 'Expiry', 'Status'],
            'rows' => array_map(fn (array $certificate): array => [
                'cells' => [$certificate['certificate_number'], $certificate['company'], $certificate['standard_code'], $certificate['issue_date'], $certificate['expiry_date'], $certificate['status']],
                'view' => site_url('certificates/verify/' . $certificate['public_slug']),
                'edit' => site_url('workflow/certification/' . $certificate['client_id'] . '/certificates'),
                'pdf' => site_url('workflow/certification/certificates/' . $certificate['id'] . '/pdf'),
            ], $certificates),
        ];
    }

    private function ncrSection(string $title, bool $includeClosed): array
    {
        $builder = Database::connect()->table('ncrs')
            ->select('ncrs.*, clients.id AS client_id, clients.company, audit_events.event_type')
            ->join('audit_events', 'audit_events.id = ncrs.audit_event_id')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->join('clients', 'clients.id = audit_programs.client_id')
            ->where('ncrs.tenant_id', (int) session()->get('tenant_id'));

        if (! $includeClosed) {
            $builder->whereNotIn('ncrs.status', ['closed', 'verified_closed', 'cancelled']);
        }

        $rows = $builder->orderBy('ncrs.id', 'DESC')->get()->getResultArray();

        return [
            'title' => $title,
            'columns' => ['NCR', 'Client', 'Audit stage', 'Class', 'Status', 'Target'],
            'rows' => array_map(fn (array $ncr): array => [
                'cells' => [$ncr['ncr_number'], $ncr['company'], str_replace('_', ' ', $ncr['event_type']), strtoupper($ncr['classification']), $ncr['status'], $ncr['target_date']],
                'view' => site_url('workflow/certification/' . $ncr['client_id'] . '/audit-events/' . $ncr['audit_event_id'] . '/file'),
                'edit' => site_url('workflow/certification/' . $ncr['client_id'] . '/audit-events/' . $ncr['audit_event_id'] . '/execute'),
                'pdf' => site_url('workflow/certification/' . $ncr['client_id'] . '/audit-events/' . $ncr['audit_event_id'] . '/documents/audit_report'),
            ], $rows),
        ];
    }

    private function applicationSection(string $title): array
    {
        $rows = Database::connect()->table('certification_applications')
            ->select('certification_applications.*, clients.company, clients.contact_person')
            ->join('clients', 'clients.id = certification_applications.client_id')
            ->where('certification_applications.tenant_id', (int) session()->get('tenant_id'))
            ->whereNotIn('certification_applications.status', ['approved', 'rejected', 'withdrawn'])
            ->where('clients.deleted_at', null)
            ->orderBy('certification_applications.id', 'DESC')
            ->get()
            ->getResultArray();

        return [
            'title' => $title,
            'columns' => ['Client', 'Application', 'Status', 'Submitted', 'Contact'],
            'rows' => array_map(fn (array $application): array => [
                'cells' => [$application['company'], $application['application_number'], $application['status'], $application['submitted_at'] ?? '', $application['contact_person']],
                'view' => site_url('workflow/certification/' . $application['client_id']),
                'edit' => site_url('workflow/certification/' . $application['client_id'] . '/application'),
                'pdf' => site_url('workflow/certification/' . $application['client_id'] . '/documents/certification_application'),
            ], $rows),
        ];
    }

    private function capaSection(string $title, bool $closed = false): array
    {
        $builder = Database::connect()->table('capas')
            ->select('capas.*, clients.id AS client_id, clients.company, audit_events.id AS audit_event_id')
            ->join('ncrs', 'ncrs.id = capas.ncr_id', 'left')
            ->join('audit_events', 'audit_events.id = ncrs.audit_event_id', 'left')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id', 'left')
            ->join('clients', 'clients.id = audit_programs.client_id', 'left')
            ->where('capas.tenant_id', (int) session()->get('tenant_id'));

        if ($closed) {
            $builder->whereIn('capas.status', ['closed', 'verified_closed']);
        } else {
            $builder->whereNotIn('capas.status', ['closed', 'verified_closed', 'cancelled']);
        }

        $rows = $builder
            ->orderBy('capas.id', 'DESC')
            ->get()
            ->getResultArray();

        return [
            'title' => $title,
            'columns' => ['CAPA', 'Client', 'Issue', 'Status', 'Target'],
            'rows' => array_map(fn (array $capa): array => [
                'cells' => [$capa['capa_number'], $capa['company'], mb_strimwidth((string) $capa['issue'], 0, 80, '...'), $capa['status'], $capa['target_date']],
                'view' => empty($capa['client_id']) ? '' : site_url('workflow/certification/' . $capa['client_id']),
                'edit' => empty($capa['client_id']) ? '' : site_url('workflow/certification/' . $capa['client_id']),
                'pdf' => empty($capa['client_id']) ? '' : site_url('workflow/certification/' . $capa['client_id'] . '/documents/audit_report'),
            ], $rows),
        ];
    }

    private function auditStatusSection(string $title, array $statuses): array
    {
        $rows = Database::connect()->table('audit_events')
            ->select('audit_events.*, clients.id AS client_id, clients.company')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->join('clients', 'clients.id = audit_programs.client_id')
            ->where('audit_programs.tenant_id', (int) session()->get('tenant_id'))
            ->whereIn('audit_events.status', $statuses)
            ->orderBy('audit_events.planned_start_date', 'DESC')
            ->get()
            ->getResultArray();

        return [
            'title' => $title,
            'columns' => ['Client', 'Audit', 'Stage', 'Start', 'End', 'Status'],
            'rows' => array_map(fn (array $event): array => [
                'cells' => [$event['company'], $event['audit_number'], str_replace('_', ' ', $event['event_type']), $event['planned_start_date'], $event['planned_end_date'], $event['status']],
                'view' => site_url('workflow/certification/' . $event['client_id'] . '/audit-events/' . $event['id'] . '/file'),
                'edit' => site_url('workflow/certification/' . $event['client_id'] . '/audit-events/' . $event['id'] . '/execute'),
                'pdf' => site_url('workflow/certification/' . $event['client_id'] . '/audit-events/' . $event['id'] . '/documents/audit_report'),
            ], $rows),
        ];
    }

    private function technicalReviewSection(string $title, string $status): array
    {
        $rows = Database::connect()->table('technical_reviews')
            ->select('technical_reviews.*, clients.id AS client_id, clients.company, audit_events.event_type, personnel.full_name')
            ->join('audit_events', 'audit_events.id = technical_reviews.audit_event_id')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->join('clients', 'clients.id = audit_programs.client_id')
            ->join('personnel', 'personnel.id = technical_reviews.reviewer_personnel_id', 'left')
            ->where('technical_reviews.tenant_id', (int) session()->get('tenant_id'))
            ->where('technical_reviews.status', $status)
            ->orderBy('technical_reviews.id', 'DESC')
            ->get()
            ->getResultArray();

        return [
            'title' => $title,
            'columns' => ['Client', 'Audit stage', 'Reviewer', 'Recommendation', 'Status'],
            'rows' => array_map(fn (array $review): array => [
                'cells' => [$review['company'], str_replace('_', ' ', $review['event_type']), $review['full_name'], str_replace('_', ' ', (string) $review['recommendation']), $review['status']],
                'view' => site_url('workflow/certification/' . $review['client_id'] . '/audit-events/' . $review['audit_event_id'] . '/file'),
                'edit' => site_url('workflow/certification/' . $review['client_id'] . '/technical-review'),
                'pdf' => site_url('workflow/certification/' . $review['client_id'] . '/audit-events/' . $review['audit_event_id'] . '/documents/technical_review'),
            ], $rows),
        ];
    }

    private function decisionSection(string $title, string $status): array
    {
        $rows = Database::connect()->table('certification_decisions')
            ->select('certification_decisions.*, technical_reviews.audit_event_id, clients.id AS client_id, clients.company, audit_events.event_type, personnel.full_name')
            ->join('technical_reviews', 'technical_reviews.id = certification_decisions.technical_review_id')
            ->join('audit_events', 'audit_events.id = technical_reviews.audit_event_id')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->join('clients', 'clients.id = audit_programs.client_id')
            ->join('personnel', 'personnel.id = certification_decisions.decision_maker_personnel_id', 'left')
            ->where('certification_decisions.tenant_id', (int) session()->get('tenant_id'))
            ->where('certification_decisions.status', $status)
            ->orderBy('certification_decisions.id', 'DESC')
            ->get()
            ->getResultArray();

        return [
            'title' => $title,
            'columns' => ['Client', 'Audit stage', 'Decision maker', 'Decision', 'Status'],
            'rows' => array_map(fn (array $decision): array => [
                'cells' => [$decision['company'], str_replace('_', ' ', $decision['event_type']), $decision['full_name'], str_replace('_', ' ', $decision['decision']), $decision['status']],
                'view' => site_url('workflow/certification/' . $decision['client_id'] . '/audit-events/' . $decision['audit_event_id'] . '/file'),
                'edit' => site_url('workflow/certification/' . $decision['client_id'] . '/decision'),
                'pdf' => site_url('workflow/certification/' . $decision['client_id'] . '/audit-events/' . $decision['audit_event_id'] . '/documents/decision_report'),
            ], $rows),
        ];
    }

    private function auditSection(string $title, string $from, string $to, array $types): array
    {
        $builder = Database::connect()->table('audit_events')
            ->select('audit_events.*, clients.id AS client_id, clients.company')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->join('clients', 'clients.id = audit_programs.client_id')
            ->where('audit_programs.tenant_id', (int) session()->get('tenant_id'))
            ->where('audit_events.planned_start_date >=', $from)
            ->where('audit_events.planned_start_date <=', $to);

        if ($types !== []) {
            $builder->whereIn('audit_events.event_type', $types);
        }

        $rows = $builder->orderBy('audit_events.planned_start_date', 'ASC')->get()->getResultArray();

        return [
            'title' => $title,
            'columns' => ['Client', 'Audit', 'Stage', 'Start', 'End', 'Status'],
            'rows' => array_map(fn (array $event): array => [
                'cells' => [$event['company'], $event['audit_number'], str_replace('_', ' ', $event['event_type']), $event['planned_start_date'], $event['planned_end_date'], $event['status']],
                'view' => site_url('workflow/certification/' . $event['client_id'] . '/audit-events/' . $event['id'] . '/file'),
                'edit' => site_url('workflow/certification/' . $event['client_id'] . '/audit-events/' . $event['id'] . '/execute'),
                'pdf' => site_url('workflow/certification/' . $event['client_id'] . '/audit-events/' . $event['id'] . '/documents/audit_plan'),
            ], $rows),
        ];
    }

    private function myAuditSection(string $title, int $personnelId, bool $closed, ?string $from = null, ?string $to = null): array
    {
        $builder = Database::connect()->table('audit_events')
            ->select("audit_events.id, audit_events.audit_program_id, audit_events.event_type, audit_events.audit_number, audit_events.planned_start_date, audit_events.planned_end_date, audit_events.actual_start_date, audit_events.actual_end_date, audit_events.audit_window_start, audit_events.audit_window_end, audit_events.duration_days, audit_events.status, audit_events.created_at, audit_events.updated_at, clients.id AS client_id, clients.company, GROUP_CONCAT(DISTINCT auditor_appointments.appointment_role ORDER BY auditor_appointments.appointment_role SEPARATOR ', ') AS appointment_role", false)
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->join('clients', 'clients.id = audit_programs.client_id')
            ->join('auditor_appointments', 'auditor_appointments.audit_event_id = audit_events.id')
            ->where('audit_programs.tenant_id', (int) session()->get('tenant_id'))
            ->where('auditor_appointments.personnel_id', $personnelId)
            ->whereIn('auditor_appointments.status', ['appointed', 'accepted', 'confirmed', 'approved', 'active']);

        if ($closed) {
            $builder->whereIn('audit_events.status', ['completed', 'closed']);
        } else {
            $builder->whereNotIn('audit_events.status', ['completed', 'closed', 'cancelled']);
        }

        if ($from !== null) {
            $builder->where('audit_events.planned_start_date >=', $from);
        }

        if ($to !== null) {
            $builder->where('audit_events.planned_start_date <=', $to);
        }

        $rows = $builder
            ->groupBy('audit_events.id, audit_events.audit_program_id, audit_events.event_type, audit_events.audit_number, audit_events.planned_start_date, audit_events.planned_end_date, audit_events.actual_start_date, audit_events.actual_end_date, audit_events.audit_window_start, audit_events.audit_window_end, audit_events.duration_days, audit_events.status, audit_events.created_at, audit_events.updated_at, clients.id, clients.company')
            ->orderBy('audit_events.planned_start_date', 'ASC')
            ->get()
            ->getResultArray();

        return [
            'title' => $title,
            'columns' => ['Client', 'Audit', 'Stage', 'Role', 'Start', 'Status'],
            'rows' => array_map(fn (array $event): array => [
                'cells' => [$event['company'], $event['audit_number'], str_replace('_', ' ', $event['event_type']), str_replace('_', ' ', $event['appointment_role']), $event['planned_start_date'], $event['status']],
                'view' => site_url('workflow/certification/' . $event['client_id'] . '/audit-events/' . $event['id'] . '/file'),
                'edit' => site_url('workflow/certification/' . $event['client_id'] . '/audit-events/' . $event['id'] . '/execute'),
                'pdf' => site_url('workflow/certification/' . $event['client_id'] . '/audit-events/' . $event['id'] . '/documents/audit_report'),
            ], $rows),
        ];
    }

    private function myNcrSection(int $personnelId): array
    {
        $rows = Database::connect()->table('ncrs')
            ->select('ncrs.*, clients.id AS client_id, clients.company, audit_events.event_type')
            ->join('audit_events', 'audit_events.id = ncrs.audit_event_id')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->join('clients', 'clients.id = audit_programs.client_id')
            ->join('auditor_appointments', 'auditor_appointments.audit_event_id = audit_events.id')
            ->where('audit_programs.tenant_id', (int) session()->get('tenant_id'))
            ->where('auditor_appointments.personnel_id', $personnelId)
            ->whereIn('auditor_appointments.status', ['appointed', 'accepted', 'confirmed', 'approved', 'active'])
            ->whereNotIn('ncrs.status', ['closed', 'verified_closed', 'cancelled'])
            ->distinct()
            ->orderBy('ncrs.id', 'DESC')
            ->get()
            ->getResultArray();

        return [
            'title' => 'My open NCRs',
            'columns' => ['NCR', 'Client', 'Audit stage', 'Class', 'Status', 'Target'],
            'rows' => array_map(fn (array $ncr): array => [
                'cells' => [$ncr['ncr_number'], $ncr['company'], str_replace('_', ' ', $ncr['event_type']), strtoupper($ncr['classification']), $ncr['status'], $ncr['target_date']],
                'view' => site_url('workflow/certification/' . $ncr['client_id'] . '/audit-events/' . $ncr['audit_event_id'] . '/file'),
                'edit' => site_url('workflow/certification/' . $ncr['client_id'] . '/audit-events/' . $ncr['audit_event_id'] . '/execute'),
                'pdf' => site_url('workflow/certification/' . $ncr['client_id'] . '/audit-events/' . $ncr['audit_event_id'] . '/documents/ncr_capa'),
            ], $rows),
        ];
    }

    private function myTechnicalReviewSection(int $personnelId): array
    {
        $rows = Database::connect()->table('technical_reviews')
            ->select('technical_reviews.*, clients.id AS client_id, clients.company, audit_events.event_type, audit_events.audit_number')
            ->join('audit_events', 'audit_events.id = technical_reviews.audit_event_id')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->join('clients', 'clients.id = audit_programs.client_id')
            ->where('technical_reviews.tenant_id', (int) session()->get('tenant_id'))
            ->where('technical_reviews.reviewer_personnel_id', $personnelId)
            ->orderBy('technical_reviews.id', 'DESC')
            ->get()
            ->getResultArray();

        return [
            'title' => 'My technical reviews',
            'columns' => ['Client', 'Audit stage', 'Audit', 'Recommendation', 'Status'],
            'rows' => array_map(fn (array $review): array => [
                'cells' => [$review['company'], str_replace('_', ' ', $review['event_type']), $review['audit_number'], str_replace('_', ' ', (string) $review['recommendation']), $review['status']],
                'view' => site_url('workflow/certification/' . $review['client_id'] . '/audit-events/' . $review['audit_event_id'] . '/file'),
                'edit' => site_url('workflow/certification/' . $review['client_id'] . '/technical-review?event_id=' . $review['audit_event_id']),
                'pdf' => site_url('workflow/certification/' . $review['client_id'] . '/audit-events/' . $review['audit_event_id'] . '/documents/technical_review'),
            ], $rows),
        ];
    }

    private function myDecisionSection(int $personnelId): array
    {
        $rows = Database::connect()->table('certification_decisions')
            ->select('certification_decisions.*, technical_reviews.audit_event_id, clients.id AS client_id, clients.company, audit_events.event_type, audit_events.audit_number')
            ->join('technical_reviews', 'technical_reviews.id = certification_decisions.technical_review_id')
            ->join('audit_events', 'audit_events.id = technical_reviews.audit_event_id')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->join('clients', 'clients.id = audit_programs.client_id')
            ->where('certification_decisions.tenant_id', (int) session()->get('tenant_id'))
            ->where('certification_decisions.decision_maker_personnel_id', $personnelId)
            ->orderBy('certification_decisions.id', 'DESC')
            ->get()
            ->getResultArray();

        return [
            'title' => 'My decisions',
            'columns' => ['Client', 'Audit stage', 'Audit', 'Decision', 'Status'],
            'rows' => array_map(fn (array $decision): array => [
                'cells' => [$decision['company'], str_replace('_', ' ', $decision['event_type']), $decision['audit_number'], str_replace('_', ' ', $decision['decision']), $decision['status']],
                'view' => site_url('workflow/certification/' . $decision['client_id'] . '/audit-events/' . $decision['audit_event_id'] . '/file'),
                'edit' => site_url('workflow/certification/' . $decision['client_id'] . '/decision?event_id=' . $decision['audit_event_id']),
                'pdf' => site_url('workflow/certification/' . $decision['client_id'] . '/audit-events/' . $decision['audit_event_id'] . '/documents/decision_report'),
            ], $rows),
        ];
    }

    private function proposalSection(string $title): array
    {
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $rows = Database::connect()->table('proposals')
            ->select('proposals.*, clients.company')
            ->join('clients', 'clients.id = proposals.client_id')
            ->where('proposals.tenant_id', (int) session()->get('tenant_id'))
            ->where('proposals.created_at >=', $monthStart . ' 00:00:00')
            ->where('proposals.created_at <=', $monthEnd . ' 23:59:59')
            ->orderBy('proposals.id', 'DESC')
            ->get()
            ->getResultArray();

        return [
            'title' => $title,
            'columns' => ['Client', 'Proposal', 'Status', 'Currency', 'Grand total'],
            'rows' => array_map(fn (array $proposal): array => [
                'cells' => [$proposal['company'], $proposal['proposal_number'], $proposal['status'], $proposal['currency'], number_format((float) $proposal['grand_total'], 2)],
                'view' => site_url('workflow/certification/' . $proposal['client_id']),
                'edit' => site_url('workflow/certification/' . $proposal['client_id'] . '/proposal'),
                'pdf' => site_url('workflow/certification/' . $proposal['client_id'] . '/documents/proposal'),
            ], $rows),
        ];
    }

    private function feedbackSection(string $title): array
    {
        $rows = Database::connect()->table('client_feedback')
            ->select('client_feedback.*, clients.company')
            ->join('clients', 'clients.id = client_feedback.client_id')
            ->where('client_feedback.tenant_id', (int) session()->get('tenant_id'))
            ->orderBy('client_feedback.id', 'DESC')
            ->get()
            ->getResultArray();

        return [
            'title' => $title,
            'columns' => ['Client', 'Contact', 'Rating', 'Status', 'Submitted'],
            'rows' => array_map(fn (array $feedback): array => [
                'cells' => [$feedback['company'], $feedback['contact_name'], $feedback['overall_rating'], $feedback['status'], $feedback['submitted_at']],
                'view' => site_url('workflow/certification/' . $feedback['client_id']),
                'edit' => site_url('workflow/certification/' . $feedback['client_id'] . '/feedback'),
                'pdf' => site_url('workflow/certification/' . $feedback['client_id'] . '/documents/feedback'),
            ], $rows),
        ];
    }

    private function certificates(int $tenantId, array $where, array $extraWhere = []): array
    {
        $builder = Database::connect()->table('certificates')
            ->select('certificates.*, clients.company, standards.code AS standard_code')
            ->join('clients', 'clients.id = certificates.client_id')
            ->join('standards', 'standards.id = certificates.standard_id')
            ->where('certificates.tenant_id', $tenantId);

        foreach ($where + $extraWhere as $field => $value) {
            $builder->where($field, $value);
        }

        return $builder->orderBy('certificates.expiry_date', 'ASC')->get()->getResultArray();
    }

    private function currentPersonnelId(int $tenantId): ?int
    {
        $row = Database::connect()->table('personnel')
            ->select('id')
            ->where('tenant_id', $tenantId)
            ->where('user_id', (int) session()->get('user_id'))
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }
}
