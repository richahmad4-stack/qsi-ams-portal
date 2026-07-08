<?php

namespace App\Services;

class CertificationApplicationDefaults
{
    public const HACCP_LEGAL_REQUIREMENTS = 'Compliance with Saudi Food and Drug Authority (SFDA) regulations, Codex Alimentarius HACCP requirements (CXC 1-1969), food labeling requirements, hygiene and food safety regulations, and applicable local laws governing production, storage, and distribution of bakery and pastry products in the Kingdom of Saudi Arabia.';
    public const HACCP_PRODUCT_PROCESS_RISKS = 'Potential risks include biological, chemical and physical contamination, allergen cross-contact, improper baking or storage conditions, product contamination during handling and distribution, and failure in hygiene or HACCP control measures during production processes.';
    public const HACCP_TECHNICAL_ISSUES = 'Technical issues may include control of baking parameters, allergen management, maintaining product quality and shelf life, hygiene and sanitation control, traceability, storage conditions, and ensuring food safety throughout production and distribution processes.';
    public const HACCP_SAFETY_REQUIREMENTS = 'Implementation of food safety and hygiene practices, personnel hygiene controls, sanitation procedures, compliance with HACCP and applicable regulatory requirements.';
    public const HACCP_TECHNOLOGICAL_CONTEXT = 'The organization operates using bakery production technologies including mixing, baking, packaging and distribution processes, while complying with HACCP Codex Alimentarius (CXC 1-1969) requirements, SFDA regulations, and applicable food safety and hygiene legislation in Saudi Arabia.';

    public function applicationAnswer(string $questionKey, array $client, array $standards): ?string
    {
        $hasHaccp = $this->hasHaccp($standards);

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
            'products' => $this->productsFromScope($client),
            'services' => $this->servicesFromScope($client),
            'processes' => $this->processesFromScope($client),
            'outsourced_processes' => $this->outsourcedProcesses($client),
            'haccp_plans_processes' => (string) $this->haccpStudyCount($client),
        ];

        if (array_key_exists($questionKey, $common)) {
            return $common[$questionKey];
        }

        if (! $hasHaccp) {
            return null;
        }

        return match ($questionKey) {
            'legal_statutory_requirements' => self::HACCP_LEGAL_REQUIREMENTS,
            'product_process_risks' => self::HACCP_PRODUCT_PROCESS_RISKS,
            'technical_issues' => self::HACCP_TECHNICAL_ISSUES,
            'safety_requirements' => self::HACCP_SAFETY_REQUIREMENTS,
            'technological_regulatory_context' => self::HACCP_TECHNOLOGICAL_CONTEXT,
            default => null,
        };
    }

    public function reviewDefaults(array $client, array $standards): array
    {
        if (! $this->hasHaccp($standards)) {
            return [
                'communication_language' => 'English',
            ];
        }

        return [
            'communication_language' => 'English / Arabic',
            'legal_requirements' => self::HACCP_LEGAL_REQUIREMENTS,
            'product_process_risks' => self::HACCP_PRODUCT_PROCESS_RISKS,
            'technical_issues' => self::HACCP_TECHNICAL_ISSUES,
            'safety_requirements' => self::HACCP_SAFETY_REQUIREMENTS,
            'technological_regulatory_context' => self::HACCP_TECHNOLOGICAL_CONTEXT,
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

    private function productsFromScope(array $client): string
    {
        $scope = strtolower((string) ($client['scope'] ?? ''));

        if ($this->containsAny($scope, ['bakery', 'pastry', 'bread', 'cake'])) {
            return 'Bakery and pastry products covered by the requested HACCP certification scope.';
        }

        if ($this->containsAny($scope, ['catering', 'meal', 'kitchen', 'hospital', 'industrial camp'])) {
            return 'Chilled and hot meals covered by the requested HACCP certification scope.';
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
