<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

class WorkflowRoleService
{
    private const SUPER_ROLES = ['super_admin'];

    private const STAGE_ROLES = [
        'application_review_manage' => ['administrator', 'certification_manager', 'technical_manager', 'quality_manager'],
        'application_review_tm' => ['technical_manager'],
        'application_review_qm' => ['quality_manager'],
        'proposal_manage' => ['administrator', 'certification_manager', 'proposal_officer', 'sales_executive'],
        'contract_manage' => ['administrator', 'certification_manager', 'proposal_officer', 'sales_executive'],
        'audit_program_manage' => ['administrator', 'certification_manager', 'technical_manager'],
        'appointment_manage' => ['administrator', 'certification_manager', 'technical_manager'],
        'audit_plan_manage' => ['administrator', 'certification_manager', 'lead_auditor'],
        'audit_execute' => ['auditor', 'lead_auditor'],
        'technical_review' => ['technical_reviewer', 'technical_manager'],
        'decision' => ['certification_decision_maker'],
        'gm_approval' => ['general_manager'],
        'certificate_issue' => ['administrator', 'certification_manager', 'general_manager'],
        'feedback_manage' => ['administrator', 'certification_manager', 'quality_manager'],
    ];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function denialReason(string $stage, ?int $eventId = null, ?int $selectedPersonnelId = null): ?string
    {
        if ($this->isSuperUser()) {
            return null;
        }

        $allowedRoles = self::STAGE_ROLES[$stage] ?? [];
        if ($allowedRoles === []) {
            return 'Workflow role policy is not configured for this action.';
        }

        if (! $this->hasAnyRole($allowedRoles)) {
            return 'Your current role is not allowed to perform this workflow action.';
        }

        if ($stage === 'audit_execute' && $eventId !== null && ! $this->currentUserIsAssignedToAuditEvent($eventId)) {
            return 'Only an appointed audit team member can execute this audit action.';
        }

        if (in_array($stage, ['technical_review', 'decision'], true) && $selectedPersonnelId !== null) {
            $currentPersonnelId = $this->currentUserPersonnelId();
            if ($currentPersonnelId === null || $currentPersonnelId !== $selectedPersonnelId) {
                return 'You can only record this approval using your own linked Personnel Master record.';
            }
        }

        return null;
    }

    public function isSuperUser(): bool
    {
        return $this->hasAnyRole(self::SUPER_ROLES);
    }

    public function hasAnyRole(array $roleCodes): bool
    {
        return array_intersect($this->currentRoleCodes(), $roleCodes) !== [];
    }

    public function currentUserPersonnelId(): ?int
    {
        $userId = (int) session()->get('user_id');
        if ($userId <= 0) {
            return null;
        }

        $row = $this->db->table('personnel')
            ->select('id')
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->get(1)
            ->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    private function currentRoleCodes(): array
    {
        return array_values(array_filter(array_map('strval', (array) session()->get('role_codes'))));
    }

    private function currentUserIsAssignedToAuditEvent(int $eventId): bool
    {
        $personnelId = $this->currentUserPersonnelId();
        if ($personnelId === null) {
            return false;
        }

        return $this->db->table('auditor_appointments')
            ->where('audit_event_id', $eventId)
            ->where('personnel_id', $personnelId)
            ->whereIn('status', ['appointed', 'accepted', 'confirmed', 'approved', 'active'])
            ->countAllResults() > 0;
    }
}
