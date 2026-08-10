<?php

namespace App\Services;

class CertificationApplicationDefaults
{
    public const HACCP_LEGAL_REQUIREMENTS = 'Compliance with Saudi Food and Drug Authority (SFDA) regulations, Codex Alimentarius HACCP requirements (CXC 1-1969), applicable food labeling, hygiene, traceability, storage and distribution requirements, and relevant Saudi food safety laws.';
    public const HACCP_PRODUCT_PROCESS_RISKS = 'Potential risks include biological, chemical and physical contamination, allergen cross-contact, loss of time or temperature control, sanitation failure, traceability failure and ineffective HACCP controls.';
    public const HACCP_TECHNICAL_ISSUES = 'Technical issues include product and process controls, allergen management, shelf life, hygiene and sanitation, traceability, storage conditions and distribution controls applicable to the requested scope.';
    public const HACCP_SAFETY_REQUIREMENTS = 'Implementation of food safety and hygiene practices, personnel hygiene controls, sanitation procedures, compliance with HACCP and applicable regulatory requirements.';
    public const HACCP_TECHNOLOGICAL_CONTEXT = 'The organization operates applicable food production and control technologies while complying with Codex HACCP (CXC 1-1969), SFDA requirements and relevant Saudi food safety legislation.';

    public function applicationAnswer(string $questionKey, array $client, array $standards): ?string
    {
        $hasFoodSafety = $this->hasFoodSafety($standards);

        $common = [
            'language_of_audit' => 'English / Arabic',
            'previous_qsi_contact' => 'No',
            'qsi_contact_details' => 'Not applicable',
            'heard_about_qsi' => 'SFDA list, website, social media',
            'other_qsi_services' => 'No',
            'management_system_status' => 'Mixed',
            'implementation_status' => 'Yes',
            'internal_audit_conducted' => 'Yes',
            'management_review_conducted' => 'Yes',
            'last_management_review_meeting_conducted' => 'Yes',
            'scope_of_certification' => (string) ($client['scope'] ?? ''),
            'products' => $this->productsFromScope($client, $standards),
            'services' => $this->servicesFromScope($client),
            'processes' => $this->processesFromScope($client),
            'outsourced_processes' => $this->outsourcedProcesses($client),
            'haccp_plans_processes' => (string) $this->haccpStudyCount($client),
        ];

        if (array_key_exists($questionKey, $common)) {
            return $common[$questionKey];
        }

        if (! $hasFoodSafety) {
            return null;
        }

        return match ($questionKey) {
            'legal_statutory_requirements' => $this->foodSafetyLegalRequirements($client, $standards),
            'product_process_risks' => $this->foodSafetyRisks($client),
            'technical_issues' => $this->foodSafetyTechnicalIssues($client),
            'safety_requirements' => $this->foodSafetySafetyRequirements($standards),
            'technological_regulatory_context' => $this->foodSafetyContext($client, $standards),
            default => null,
        };
    }

    public function reviewDefaults(array $client, array $standards): array
    {
        if (! $this->hasFoodSafety($standards)) {
            return [
                'communication_language' => 'English',
            ];
        }

        return [
            'communication_language' => 'English / Arabic',
            'legal_requirements' => $this->foodSafetyLegalRequirements($client, $standards),
            'product_process_risks' => $this->foodSafetyRisks($client),
            'technical_issues' => $this->foodSafetyTechnicalIssues($client),
            'safety_requirements' => $this->foodSafetySafetyRequirements($standards),
            'technological_regulatory_context' => $this->foodSafetyContext($client, $standards),
            'outsourced_activity_details' => $this->outsourcedProcesses($client),
            'haccp_plans_processes' => (string) $this->haccpStudyCount($client),
        ];
    }

    public function hasHaccp(array $standards): bool
    {
        foreach ($standards as $standard) {
            $code = strtoupper((string) ($standard['standard_code'] ?? $standard['code'] ?? ''));
            if (str_contains($code, 'HACCP')) {
                return true;
            }
        }

        return false;
    }

    public function hasFoodSafety(array $standards): bool
    {
        foreach ($standards as $standard) {
            $code = strtoupper((string) ($standard['standard_code'] ?? $standard['code'] ?? ''));
            if (str_contains($code, 'HACCP') || str_contains($code, 'ISO 22000')) {
                return true;
            }
        }

        return false;
    }

    private function foodSafetyLegalRequirements(array $client, array $standards): string
    {
        $criteria = $this->hasHaccp($standards)
            ? 'Codex Alimentarius HACCP requirements (CXC 1-1969)'
            : 'ISO 22000:2018 food safety management system requirements';

        if ($this->hasHaccp($standards) && $this->hasIso22000($standards)) {
            $criteria = 'ISO 22000:2018 and Codex Alimentarius HACCP requirements (CXC 1-1969)';
        }

        return 'Compliance with Saudi Food and Drug Authority (SFDA) regulations, ' . $criteria
            . ', applicable food labeling, hygiene, traceability, storage and distribution requirements, and Saudi laws relevant to '
            . strtolower($this->productsFromScope($client, $standards));
    }

    private function foodSafetyRisks(array $client): string
    {
        $processes = strtolower(rtrim($this->processesFromScope($client), ". \t\n\r\0\x0B"));

        return 'Potential biological, chemical, physical and allergen hazards associated with ' . $processes
            . ', including loss of time/temperature control, cross-contamination, sanitation failure, traceability failure and ineffective food safety controls.';
    }

    private function foodSafetyTechnicalIssues(array $client): string
    {
        return 'Technical review shall consider control of ' . strtolower(rtrim($this->processesFromScope($client), ". \t\n\r\0\x0B"))
            . ', product characteristics and shelf life, PRPs, allergen management, sanitation, traceability, storage conditions and distribution controls applicable to the requested scope.';
    }

    private function foodSafetySafetyRequirements(array $standards): string
    {
        $criteria = $this->hasHaccp($standards) && $this->hasIso22000($standards)
            ? 'ISO 22000:2018 and Codex HACCP'
            : ($this->hasHaccp($standards) ? 'Codex HACCP' : 'ISO 22000:2018');

        return 'Implementation of food safety and hygiene practices, personnel hygiene controls, sanitation procedures, emergency readiness and compliance with ' . $criteria . ' and applicable regulatory requirements.';
    }

    private function foodSafetyContext(array $client, array $standards): string
    {
        $criteria = $this->hasHaccp($standards) && $this->hasIso22000($standards)
            ? 'ISO 22000:2018 and Codex HACCP (CXC 1-1969)'
            : ($this->hasHaccp($standards) ? 'Codex HACCP (CXC 1-1969)' : 'ISO 22000:2018');

        return 'The organization operates the processes of ' . strtolower(rtrim($this->processesFromScope($client), ". \t\n\r\0\x0B"))
            . ' under applicable food production and control technologies, while complying with ' . $criteria
            . ', SFDA requirements and relevant Saudi food safety legislation.';
    }

    private function hasIso22000(array $standards): bool
    {
        foreach ($standards as $standard) {
            if (str_contains(strtoupper((string) ($standard['standard_code'] ?? $standard['code'] ?? '')), 'ISO 22000')) {
                return true;
            }
        }

        return false;
    }

    private function productsFromScope(array $client, array $standards = []): string
    {
        $scope = strtolower((string) ($client['scope'] ?? ''));
        $scheme = $standards !== [] && ! $this->hasHaccp($standards) ? 'food safety' : 'HACCP';

        if ($this->containsAny($scope, ['bakery', 'pastry', 'bread', 'cake'])) {
            return 'Bakery and pastry products covered by the requested ' . $scheme . ' certification scope.';
        }

        if ($this->containsAny($scope, ['catering', 'meal', 'kitchen', 'hospital', 'industrial camp'])) {
            return 'Chilled and hot meals covered by the requested ' . $scheme . ' certification scope.';
        }

        if ($this->containsAny($scope, ['dairy', 'milk', 'cheese', 'yoghurt', 'yogurt'])) {
            return 'Dairy products covered by the requested food safety certification scope.';
        }

        if ($this->containsAny($scope, ['seafood', 'fish', 'shrimp'])) {
            return 'Seafood products covered by the requested food safety certification scope.';
        }

        return 'Food products covered by the requested certification scope.';
    }

    private function servicesFromScope(array $client): string
    {
        $scope = strtolower((string) ($client['scope'] ?? ''));

        if ($this->containsAny($scope, ['delivery', 'distribution', 'dispatch'])) {
            return 'Preparation, handling, storage, dispatch and delivery services related to the certified food scope.';
        }

        return 'Food preparation, handling, storage and related support services within the requested certification scope.';
    }

    private function processesFromScope(array $client): string
    {
        $scope = strtolower((string) ($client['scope'] ?? ''));

        if ($this->containsAny($scope, ['bakery', 'pastry', 'bread', 'cake'])) {
            return 'Receiving of raw materials, storage, mixing, preparation, baking, cooling, packing, labeling, finished product storage and dispatch.';
        }

        if ($this->containsAny($scope, ['catering', 'meal', 'kitchen'])) {
            return 'Receiving, storage, preparation, cooking or hot holding, chilling where applicable, packing, dispatch and delivery control.';
        }

        if ($this->containsAny($scope, ['dairy', 'milk', 'cheese', 'yoghurt', 'yogurt'])) {
            return 'Receiving, storage, processing, pasteurization or heat treatment where applicable, packing, cold storage and dispatch.';
        }

        return 'Receiving, storage, processing or preparation, packing, finished product storage and dispatch as applicable to the certified scope.';
    }

    private function outsourcedProcesses(array $client): string
    {
        $declared = trim((string) ($client['outsourced_processes'] ?? ''));
        if ($declared !== '') {
            return $declared;
        }

        return 'No outsourced process declared at application stage; to be verified during application review and audit planning.';
    }

    private function haccpStudyCount(array $client): int
    {
        $scope = strtolower((string) ($client['scope'] ?? ''));
        $count = 1;

        if (str_contains($scope, 'chilled') && str_contains($scope, 'hot')) {
            $count = 2;
        }

        if (str_contains($scope, 'multiple') || str_contains($scope, 'various')) {
            $count = max($count, 2);
        }

        if (preg_match_all('/\band\b|[,;\/]/', $scope) > 3) {
            $count = max($count, 2);
        }

        return $count;
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }
}
