<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ClauseLibraryPlaceholderSeeder extends Seeder
{
    private int $tenantId = 1;

    public function run(): void
    {
        $standards = $this->db->table('standards')
            ->where('active', 1)
            ->orderBy('code', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($standards as $standard) {
            foreach ($this->clausesFor((string) $standard['code'], (string) $standard['scheme_type']) as $clause) {
                $this->insertClause((int) $standard['id'], (string) $standard['code'], $clause);
            }
        }
    }

    private function clausesFor(string $standardCode, string $schemeType): array
    {
        if (str_contains($standardCode, 'ISO 17021')) {
            return $this->certificationBodyClauses();
        }

        if (str_contains($standardCode, 'ISO 17065')) {
            return $this->productCertificationClauses();
        }

        if ($schemeType === 'food_safety' || str_contains($standardCode, 'HACCP') || str_contains($standardCode, 'FSSC')) {
            return array_merge($this->managementSystemClauses(), $this->foodSafetyClauses());
        }

        if ($schemeType === 'medical_device') {
            return array_merge($this->managementSystemClauses(), $this->medicalDeviceClauses());
        }

        return $this->managementSystemClauses();
    }

    private function managementSystemClauses(): array
    {
        return [
            ['4.1', 'Context review', 'strategic and operational context'],
            ['4.2', 'Interested party needs', 'interested party requirements'],
            ['4.3', 'Scope definition', 'management system scope'],
            ['4.4', 'Management system processes', 'process interactions and controls'],
            ['5.1', 'Leadership commitment', 'leadership involvement and accountability'],
            ['5.2', 'Policy control', 'policy availability and communication'],
            ['5.3', 'Roles and responsibilities', 'assigned responsibilities and authorities'],
            ['6.1', 'Risks and opportunities', 'risk and opportunity planning'],
            ['6.2', 'Objectives and planning', 'measurable objectives and action plans'],
            ['6.3', 'Change planning', 'planned management system changes'],
            ['7.1', 'Resources', 'resources needed for effective operation'],
            ['7.2', 'Competence', 'competence requirements and evidence'],
            ['7.3', 'Awareness', 'awareness of policy, objectives and contribution'],
            ['7.4', 'Communication', 'internal and external communication controls'],
            ['7.5', 'Documented information', 'document control and retained evidence'],
            ['8.1', 'Operational planning and control', 'operational controls and criteria'],
            ['8.2', 'Customer and requirement review', 'review of requirements before commitment'],
            ['8.3', 'Design and development control', 'design controls where applicable'],
            ['8.4', 'Externally provided processes', 'supplier and outsourced process controls'],
            ['8.5', 'Production or service provision', 'controlled operational delivery'],
            ['8.6', 'Release of outputs', 'release checks and acceptance evidence'],
            ['8.7', 'Nonconforming outputs', 'control of nonconforming product or service'],
            ['9.1', 'Monitoring and measurement', 'performance monitoring and analysis'],
            ['9.2', 'Internal audit', 'internal audit planning, execution and follow-up'],
            ['9.3', 'Management review', 'management review inputs, outputs and actions'],
            ['10.1', 'Improvement', 'improvement opportunities and actions'],
            ['10.2', 'Nonconformity and corrective action', 'correction, cause analysis and action'],
            ['10.3', 'Continual improvement', 'ongoing improvement of system effectiveness'],
        ];
    }

    private function foodSafetyClauses(): array
    {
        return [
            ['FS.1', 'Food safety hazards', 'hazard identification and control measures'],
            ['FS.2', 'Prerequisite programs', 'PRP implementation and monitoring'],
            ['FS.3', 'HACCP plan', 'critical controls, limits and monitoring'],
            ['FS.4', 'Traceability and withdrawal', 'traceability, recall and withdrawal tests'],
            ['FS.5', 'Emergency preparedness', 'food safety emergency readiness'],
        ];
    }

    private function medicalDeviceClauses(): array
    {
        return [
            ['MD.1', 'Regulatory requirements', 'applicable medical device regulatory controls'],
            ['MD.2', 'Risk management', 'device risk management and lifecycle evidence'],
            ['MD.3', 'Sterile or cleanliness controls', 'cleanliness, contamination and sterile controls where applicable'],
            ['MD.4', 'Complaint and vigilance handling', 'post-market feedback and complaint control'],
            ['MD.5', 'Validation and traceability', 'process validation, identification and traceability'],
        ];
    }

    private function certificationBodyClauses(): array
    {
        return [
            ['4.1', 'Legal and contractual matters', 'legal responsibility and enforceable agreements'],
            ['4.2', 'Impartiality management', 'impartiality risks, controls and committee oversight'],
            ['4.3', 'Liability and financing', 'liability coverage and financial stability'],
            ['5.1', 'Organizational structure', 'structure, duties and authority'],
            ['6.1', 'Personnel competence', 'competence criteria, evaluation and monitoring'],
            ['6.2', 'External resources', 'outsourced resources and controls'],
            ['7.1', 'Certification process', 'application, review, audit, decision and certification cycle'],
            ['7.2', 'Application review', 'scope, competence, resources and capability review'],
            ['7.3', 'Audit planning', 'audit programme, audit plan and team assignment'],
            ['7.4', 'Audit execution', 'audit conduct, evidence, findings and reporting'],
            ['7.5', 'Review and decision', 'independent review and certification decision'],
            ['7.6', 'Certificate documents', 'certificate content and control'],
            ['7.7', 'Surveillance', 'surveillance programme and monitoring'],
            ['7.8', 'Suspension or withdrawal', 'suspension, withdrawal and scope changes'],
            ['8.1', 'Management system', 'management system for certification body operations'],
            ['9.1', 'Internal audit', 'internal audit of certification body processes'],
            ['9.2', 'Management review', 'review of certification body performance and impartiality'],
            ['10.1', 'Corrective action', 'correction, cause analysis and effectiveness'],
        ];
    }

    private function productCertificationClauses(): array
    {
        return [
            ['4.1', 'General requirements', 'legal responsibility, agreement and impartiality'],
            ['5.1', 'Structural requirements', 'organization, responsibilities and impartiality mechanism'],
            ['6.1', 'Resource requirements', 'personnel, facilities and competence controls'],
            ['6.2', 'Evaluation resources', 'internal and external evaluation resources'],
            ['7.1', 'Process requirements', 'application, evaluation, review, decision and certification'],
            ['7.2', 'Evaluation', 'evaluation planning, sampling, testing and inspection controls'],
            ['7.3', 'Review', 'review of evaluation results'],
            ['7.4', 'Certification decision', 'independent certification decision'],
            ['7.5', 'Certificate control', 'certificate documents and directory'],
            ['7.6', 'Surveillance', 'surveillance where required by the scheme'],
            ['7.7', 'Changes affecting certification', 'scheme and product change controls'],
            ['8.1', 'Management system option', 'management system controls for certification body'],
            ['8.2', 'Documentation control', 'documented information control'],
            ['8.3', 'Internal audit and review', 'internal audit and management review'],
            ['8.4', 'Corrective action', 'nonconformity and corrective action controls'],
        ];
    }

    private function insertClause(int $standardId, string $standardCode, array $clause): void
    {
        [$number, $title, $focus] = $clause;

        $existing = $this->db->table('clause_library')
            ->where('tenant_id', $this->tenantId)
            ->where('standard_id', $standardId)
            ->where('clause_number', $number)
            ->get(1)
            ->getRowArray();

        if ($existing !== null) {
            return;
        }

        $this->db->table('clause_library')->insert([
            'tenant_id' => $this->tenantId,
            'standard_id' => $standardId,
            'clause_number' => $number,
            'clause_title' => $title,
            'requirement' => 'Placeholder for licensed ' . $standardCode . ' requirement text covering ' . $focus . '. Replace with approved licensed clause wording or internal checklist questions.',
            'predefined_conformity_note' => 'Evidence reviewed indicates the process for ' . $focus . ' is defined, implemented and maintained.',
            'positive_finding' => 'The organization demonstrated effective practice in ' . $focus . ', with clear evidence and responsible ownership.',
            'opportunity_for_improvement' => 'The organization may improve consistency, evidence retention or performance monitoring related to ' . $focus . '.',
            'minor_nc' => 'Isolated weakness or incomplete evidence was found in ' . $focus . ', with limited impact on system effectiveness.',
            'major_nc' => 'Systemic absence, repeated failure or ineffective control was found in ' . $focus . ', affecting confidence in system effectiveness.',
            'evidence_examples' => 'Policies, procedures, records, interviews, sampled transactions, monitoring results, corrective actions and management review outputs.',
            'auditor_guidance' => 'Verify implementation through records, interviews and sampling. Confirm ownership, criteria, evidence retention and follow-up of actions.',
            'risk_rating' => 'medium',
            'stage_applicability' => 'stage1,stage2,surveillance,recertification',
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
