<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SeedActualPersonnel extends Migration
{
    public function up()
    {
        $tenant = $this->db->table('tenants')->where('code', 'QSI')->get(1)->getRowArray();
        if ($tenant === null) {
            return;
        }

        $tenantId = (int) $tenant['id'];
        $now = date('Y-m-d H:i:s');

        $this->softRemoveDemoUsers($tenantId, $now);
        $this->ensureRoles($tenantId);

        $people = [
            ['Dr. Rana Amjad Hanif', 'Canada', ['chief_executive_officer', 'general_manager', 'lead_auditor', 'certification_decision_maker', 'technical_manager']],
            ['Eng. Mohammad Ahmad', 'Pakistan', ['chief_operating_officer', 'quality_manager', 'lead_auditor', 'technical_manager', 'certification_decision_maker']],
            ['Rana Arslan Khan', 'Canada', ['administrator', 'super_admin', 'lead_auditor']],
            ['Mr. Rifki El-Sherbeny', 'Egypt', ['lead_auditor']],
            ['Mohammad Arshad Ali', 'India', ['lead_auditor']],
            ['Mohammad Raheel', 'Pakistan', ['lead_auditor', 'technical_manager']],
            ['Ms. Rimsha Mahmoud', 'Pakistan', ['technical_manager']],
            ['Qammar Shahzad', 'Pakistan', ['lead_auditor']],
        ];

        foreach ($people as [$name, $nationality, $roles]) {
            $email = $this->emailFor($name);
            $roleIds = $this->roleIds($tenantId, $roles);
            $primaryRoleId = $roleIds[0] ?? null;
            $userId = $this->upsertUser($tenantId, $name, $email, $primaryRoleId);
            $this->syncRoles($userId, $roleIds);
            $personnelId = $this->upsertPersonnel($tenantId, $userId, $name, $email, $nationality, $roles);
            $this->ensureAllStandardCompetence($personnelId, $roles);
        }
    }

    public function down()
    {
        $tenant = $this->db->table('tenants')->where('code', 'QSI')->get(1)->getRowArray();
        if ($tenant === null) {
            return;
        }

        $emails = array_map([$this, 'emailFor'], [
            'Dr. Rana Amjad Hanif',
            'Eng. Mohammad Ahmad',
            'Rana Arslan Khan',
            'Mr. Rifki El-Sherbeny',
            'Mohammad Arshad Ali',
            'Mohammad Raheel',
            'Ms. Rimsha Mahmoud',
            'Qammar Shahzad',
        ]);
        $userIds = array_column($this->db->table('users')->select('id')->where('tenant_id', (int) $tenant['id'])->whereIn('email', $emails)->get()->getResultArray(), 'id');
        $personnelIds = $userIds === [] ? [] : array_column($this->db->table('personnel')->select('id')->whereIn('user_id', $userIds)->get()->getResultArray(), 'id');

        if ($personnelIds !== []) {
            $this->db->table('personnel_competencies')->whereIn('personnel_id', $personnelIds)->delete();
            $this->db->table('personnel')->whereIn('id', $personnelIds)->delete();
        }
        if ($userIds !== []) {
            $this->db->table('user_role_assignments')->whereIn('user_id', $userIds)->delete();
            $this->db->table('users')->whereIn('id', $userIds)->delete();
        }
    }

    private function softRemoveDemoUsers(int $tenantId, string $now): void
    {
        $users = $this->db->table('users')
            ->select('id')
            ->where('tenant_id', $tenantId)
            ->groupStart()
                ->like('email', 'demo.', 'after')
                ->orLike('email', '@demo-qsi.test', 'before')
                ->orLike('full_name', 'Demo ', 'both')
                ->orWhere('full_name', 'QSI Demo Administrator')
            ->groupEnd()
            ->get()
            ->getResultArray();

        $userIds = array_column($users, 'id');
        if ($userIds === []) {
            return;
        }

        $personnelIds = array_column($this->db->table('personnel')->select('id')->whereIn('user_id', $userIds)->get()->getResultArray(), 'id');
        if ($personnelIds !== []) {
            $this->db->table('personnel')->whereIn('id', $personnelIds)->update(['approval_status' => 'inactive', 'deleted_at' => $now]);
        }
        $this->db->table('users')->whereIn('id', $userIds)->update(['status' => 'inactive', 'deleted_at' => $now]);
    }

    private function ensureRoles(int $tenantId): void
    {
        $roles = [
            'chief_executive_officer' => 'Chief Executive Officer',
            'chief_operating_officer' => 'Chief Operating Officer',
            'general_manager' => 'General Manager',
            'quality_manager' => 'Quality Manager',
            'lead_auditor' => 'Lead Auditor',
            'certification_decision_maker' => 'Decision Maker',
            'technical_manager' => 'Technical Manager',
            'administrator' => 'Administrator',
            'super_admin' => 'Super Admin',
        ];

        foreach ($roles as $code => $name) {
            $exists = $this->db->table('roles')->where('tenant_id', $tenantId)->where('code', $code)->countAllResults();
            if ($exists > 0) {
                continue;
            }
            $this->db->table('roles')->insert([
                'tenant_id' => $tenantId,
                'name' => $name,
                'code' => $code,
                'description' => $name . ' role.',
                'system_role' => 1,
            ]);
        }
    }

    private function roleIds(int $tenantId, array $codes): array
    {
        $rows = $this->db->table('roles')->select('id, code')->where('tenant_id', $tenantId)->whereIn('code', $codes)->get()->getResultArray();
        $byCode = [];
        foreach ($rows as $row) {
            $byCode[(string) $row['code']] = (int) $row['id'];
        }

        return array_values(array_filter(array_map(static fn (string $code): ?int => $byCode[$code] ?? null, $codes)));
    }

    private function upsertUser(int $tenantId, string $name, string $email, ?int $primaryRoleId): int
    {
        $existing = $this->db->table('users')->select('id')->where('tenant_id', $tenantId)->where('email', $email)->get(1)->getRowArray();
        $data = [
            'primary_role_id' => $primaryRoleId,
            'full_name' => $name,
            'email' => $email,
            'password_hash' => password_hash('ChangeMe@123', PASSWORD_DEFAULT),
            'status' => 'active',
            'must_change_password' => 1,
            'deleted_at' => null,
        ];

        if ($existing !== null) {
            $this->db->table('users')->where('id', (int) $existing['id'])->update($data);
            return (int) $existing['id'];
        }

        $data['tenant_id'] = $tenantId;
        $this->db->table('users')->insert($data);

        return (int) $this->db->insertID();
    }

    private function syncRoles(int $userId, array $roleIds): void
    {
        foreach ($roleIds as $roleId) {
            $exists = $this->db->table('user_role_assignments')->where('user_id', $userId)->where('role_id', $roleId)->countAllResults();
            if ($exists === 0) {
                $this->db->table('user_role_assignments')->insert(['user_id' => $userId, 'role_id' => $roleId]);
            }
        }
    }

    private function upsertPersonnel(int $tenantId, int $userId, string $name, string $email, string $nationality, array $roles): int
    {
        $type = in_array('lead_auditor', $roles, true) ? 'lead auditor' : (in_array('technical_manager', $roles, true) ? 'technical reviewer' : 'staff');
        $existing = $this->db->table('personnel')->select('id')->where('tenant_id', $tenantId)->where('email', $email)->get(1)->getRowArray();
        $data = [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'client_id' => null,
            'full_name' => $name,
            'email' => $email,
            'phone' => '',
            'personnel_type' => $type,
            'approval_status' => 'approved',
            'languages' => json_encode(['English'], JSON_THROW_ON_ERROR),
            'countries' => json_encode([$nationality], JSON_THROW_ON_ERROR),
            'experience_summary' => 'Approved QSI personnel. Roles: ' . implode(', ', array_map(static fn (string $role): string => str_replace('_', ' ', $role), $roles)) . '.',
            'deleted_at' => null,
        ];

        if ($existing !== null) {
            $this->db->table('personnel')->where('id', (int) $existing['id'])->update($data);
            return (int) $existing['id'];
        }

        $this->db->table('personnel')->insert($data);

        return (int) $this->db->insertID();
    }

    private function ensureAllStandardCompetence(int $personnelId, array $roles): void
    {
        if (! array_intersect($roles, ['lead_auditor', 'technical_manager', 'certification_decision_maker', 'quality_manager'])) {
            return;
        }

        $exists = $this->db->table('personnel_competencies')
            ->where('personnel_id', $personnelId)
            ->where('standard_id', null)
            ->where('competency_type', 'all_standards')
            ->countAllResults();
        if ($exists > 0) {
            return;
        }

        $this->db->table('personnel_competencies')->insert([
            'personnel_id' => $personnelId,
            'standard_id' => null,
            'iaf_code_id' => null,
            'food_chain_category_id' => null,
            'medical_device_category_id' => null,
            'competency_type' => 'all_standards',
            'valid_from' => date('Y-m-d'),
            'valid_until' => null,
            'approval_status' => 'approved',
            'evidence_notes' => 'Approved for all current certification schemes pending controlled competence file update.',
        ]);
    }

    private function emailFor(string $name): string
    {
        $name = strtolower(trim(preg_replace('/\b(dr|eng|mr|ms)\.?\b/i', '', $name)));
        $name = trim(preg_replace('/[^a-z0-9]+/', '.', $name), '.');

        return $name . '@qsi.local';
    }
}
