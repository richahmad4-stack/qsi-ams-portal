<?php

namespace App\Database\Seeds;

use App\Services\CycleAutomationService;
use CodeIgniter\Database\Seeder;
use RuntimeException;

class Demo2024FoodSafetyCycleSeeder extends Seeder
{
    private const TENANT_ID = 1;

    public function run(): void
    {
        $userId = $this->superAdminUserId();
        $standardIds = $this->standardIds();
        $service = new CycleAutomationService($this->db);
        $failures = [];

        foreach ($this->cycles($standardIds) as $input) {
            $existing = $this->db->table('clients')
                ->select('id')
                ->where('tenant_id', self::TENANT_ID)
                ->where('company', $input['client_name'])
                ->where('certificate_issue_date', $input['certificate_issue_date'])
                ->get(1)
                ->getRowArray();

            if ($existing !== null) {
                echo '[SKIP] ' . $input['client_name'] . ' already exists as client #' . (int) $existing['id'] . PHP_EOL;
                continue;
            }

            try {
                $preview = $service->preview($input, self::TENANT_ID, $userId);
                if (! ($preview['can_generate'] ?? false)) {
                    $messages = array_map(
                        static fn (array $warning): string => (string) ($warning['message'] ?? 'Unknown control warning'),
                        array_filter(
                            $preview['warnings'] ?? [],
                            static fn (array $warning): bool => ($warning['level'] ?? '') === 'critical'
                        )
                    );

                    throw new RuntimeException(implode(' | ', $messages));
                }

                $result = $service->generate($preview, self::TENANT_ID, $userId);
                echo '[SEEDED] ' . $input['client_name'] . ' as client #' . (int) $result['client_id'] . PHP_EOL;
            } catch (\Throwable $exception) {
                $failures[] = $input['client_name'] . ': ' . $exception->getMessage();
                echo '[FAILED] ' . end($failures) . PHP_EOL;
            }
        }

        if ($failures !== []) {
            throw new RuntimeException('One or more 2024 demo cycles could not be prepared: ' . implode(' || ', $failures));
        }
    }

    private function superAdminUserId(): int
    {
        $users = $this->db->table('users')
            ->select('users.id, users.email')
            ->join('user_role_assignments', 'user_role_assignments.user_id = users.id')
            ->join('roles', 'roles.id = user_role_assignments.role_id')
            ->where('users.tenant_id', self::TENANT_ID)
            ->where('users.status', 'active')
            ->where('users.deleted_at', null)
            ->where('roles.code', 'super_admin')
            ->get()
            ->getResultArray();

        foreach ($users as $user) {
            if (strtolower((string) $user['email']) === 'rana.arslan.khan@qsi.local') {
                return (int) $user['id'];
            }
        }

        if ($users !== []) {
            return (int) $users[0]['id'];
        }

        throw new RuntimeException('No active Super Admin account was found for tenant 1.');
    }

    private function standardIds(): array
    {
        $rows = $this->db->table('standards')
            ->select('id, code')
            ->where('active', 1)
            ->whereIn('code', ['HACCP', 'ISO 22000:2018'])
            ->get()
            ->getResultArray();
        $ids = [];

        foreach ($rows as $row) {
            $ids[(string) $row['code']] = (int) $row['id'];
        }

        foreach (['HACCP', 'ISO 22000:2018'] as $requiredCode) {
            if (! isset($ids[$requiredCode])) {
                throw new RuntimeException('Required active standard is missing: ' . $requiredCode);
            }
        }

        return $ids;
    }

    private function cycles(array $standardIds): array
    {
        return [
            [
                'client_name' => 'Demo 2024 Riyadh Central Catering LLC',
                'client_address' => 'Industrial Catering District, Riyadh, Saudi Arabia',
                'contact_person' => 'Faisal Al Qahtani',
                'designation' => 'Food Safety Team Leader',
                'email' => 'demo.2024.haccp@qsi.test',
                'phone' => '+966 11 555 2401',
                'standard_ids' => [$standardIds['HACCP']],
                'scope' => 'Preparation and delivery of chilled and hot meals for hospitals, offices and industrial camps.',
                'employee_count' => 35,
                'number_of_sites' => 1,
                'certificate_issue_date' => '2024-01-15',
                'certification_status' => 'certified',
                'current_cycle_stage' => 'auto',
                'risk_category' => 'high',
                'ncr_mode' => 'sample_minor',
                'generation_mode' => 'standard',
                'special_notes' => 'Controlled 2024 HACCP demo client for end-to-end functional testing. Two HACCP studies cover chilled meals and hot meals.',
                'application_review_notes' => 'HACCP certification application reviewed for catering operations, two HACCP studies, statutory requirements, hygiene controls and operational readiness.',
                'audit_plan_notes' => 'Cover receiving, storage, preparation, cooking, chilling, hot holding, dispatch, hygiene, traceability and the two approved HACCP studies.',
            ],
            [
                'client_name' => 'Demo 2024 Fresh Valley Dairy Factory',
                'client_address' => 'Food Processing Industrial Area, Al Kharj, Saudi Arabia',
                'contact_person' => 'Noura Al Mutairi',
                'designation' => 'Food Safety Management Representative',
                'email' => 'demo.2024.iso22000@qsi.test',
                'phone' => '+966 11 555 2402',
                'standard_ids' => [$standardIds['ISO 22000:2018']],
                'scope' => 'Processing, pasteurization, packing, cold storage and distribution of milk and dairy products.',
                'employee_count' => 60,
                'number_of_sites' => 1,
                'certificate_issue_date' => '2024-03-18',
                'certification_status' => 'certified',
                'current_cycle_stage' => 'auto',
                'risk_category' => 'high',
                'ncr_mode' => 'sample_minor',
                'generation_mode' => 'standard',
                'special_notes' => 'Controlled 2024 ISO 22000 demo client for end-to-end functional testing.',
                'application_review_notes' => 'ISO 22000 application reviewed for dairy processing, food safety hazards, PRPs, traceability, statutory controls and operational readiness.',
                'audit_plan_notes' => 'Cover raw milk receiving, pasteurization, filling, cold storage, laboratory control, traceability, PRPs and food safety management system processes.',
            ],
            [
                'client_name' => 'Demo 2024 Gulf Ready Meals Industries',
                'client_address' => 'Second Industrial City, Dammam, Saudi Arabia',
                'contact_person' => 'Khalid Al Harbi',
                'designation' => 'Quality and Food Safety Manager',
                'email' => 'demo.2024.integrated.food@qsi.test',
                'phone' => '+966 13 555 2403',
                'standard_ids' => [$standardIds['ISO 22000:2018'], $standardIds['HACCP']],
                'scope' => 'Manufacture, packing, cold storage and distribution of chilled and frozen ready meals.',
                'employee_count' => 85,
                'number_of_sites' => 1,
                'certificate_issue_date' => '2024-05-20',
                'certification_status' => 'certified',
                'current_cycle_stage' => 'auto',
                'risk_category' => 'high',
                'ncr_mode' => 'sample_minor',
                'generation_mode' => 'standard',
                'special_notes' => 'Controlled 2024 combined HACCP and ISO 22000 demo client for integrated file and report testing. Four HACCP studies cover chilled and frozen product families.',
                'application_review_notes' => 'Combined HACCP and ISO 22000 application reviewed for ready-meal production, four HACCP studies, PRPs, statutory controls and integrated audit readiness.',
                'audit_plan_notes' => 'Use an integrated plan covering receiving, preparation, cooking, chilling/freezing, packing, cold storage, dispatch, PRPs, FSMS processes and four HACCP studies without duplicating common controls.',
            ],
        ];
    }
}
