<?php

namespace App\Services;

use DateInterval;
use DateTimeImmutable;

class AuditDurationService
{
    private const MULTI_STANDARD_ADDITION_FACTOR = 0.20;
    private const MAX_REDUCTION_FACTOR = 0.70;
    private const REFERENCE_NOTE = 'Controlled QSI audit-duration rule set aligned with ISO/IEC 17021-1 competence/impartiality controls and IAF MD 5 / IAF MD 11 audit-time principles. Food safety values use scheme-specific complexity factors and must be verified against the current licensed scheme rules before accreditation use.';

    public function calculateInitialDays(array $client, array $standards = []): array
    {
        return $this->calculateApplicationReview($client, $standards, []);
    }

    public function calculateApplicationReview(array $client, array $standards = [], array $reviewInputs = []): array
    {
        $employeeCount = $this->effectiveEmployeeCount($client, $reviewInputs);
        $standardCodes = $this->standardCodes($standards, (string) ($reviewInputs['standards_text'] ?? ''));
        $schemes = $this->schemeTypes($standards, $standardCodes);
        $standardTotals = [];
        $standardRules = [];

        foreach ($standardCodes as $code) {
            $rule = $this->ruleForStandard($code, $schemes[$code] ?? '', $employeeCount, $reviewInputs);
            $standardTotals[$code] = $rule['days'];
            $standardRules[$code] = $rule;
        }

        if ($standardTotals === []) {
            $rule = $this->ruleForStandard('GENERAL', 'management_system', $employeeCount, $reviewInputs);
            $standardTotals['GENERAL'] = $rule['days'];
            $standardRules['GENERAL'] = $rule;
        }

        arsort($standardTotals);
        $base = (float) reset($standardTotals);
        $additional = array_slice($standardTotals, 1, null, true);
        $integratedAddition = 0.00;
        foreach ($additional as $days) {
            $integratedAddition += (float) $days * self::MULTI_STANDARD_ADDITION_FACTOR;
        }

        $factor = 1.00;
        $factors = [];
        $risk = strtolower((string) ($reviewInputs['risk_classification'] ?? $client['risk_category'] ?? ''));
        if ($risk === 'high') {
            $factor += 0.10;
            $factors[] = 'High risk +10%';
        } elseif ($risk === 'low') {
            $factor -= 0.10;
            $factors[] = 'Low risk -10%';
        }

        $shiftText = strtolower((string) ($reviewInputs['shifts_auditing'] ?? $client['shift_pattern'] ?? ''));
        if ($shiftText !== '' && ! in_array($shiftText, ['one', '1', 'single', 'no', 'n/a', 'na'], true)) {
            $factor += 0.10;
            $factors[] = 'Multiple shifts +10%';
        }

        $siteCount = max(1, (int) ($client['number_of_sites'] ?? 1));
        if ($siteCount > 1) {
            $factor += min(0.30, ($siteCount - 1) * 0.05);
            $factors[] = 'Multiple sites +' . number_format(min(30, ($siteCount - 1) * 5), 0) . '%';
        }

        $reductionPercent = max(0.00, min(30.00, (float) ($reviewInputs['reduction_percentage'] ?? 0)));
        if ($reductionPercent > 0) {
            $factor -= $reductionPercent / 100;
            $factors[] = 'Approved reduction -' . number_format($reductionPercent, 2) . '%';
        }

        $total = $this->roundHalfDay(max(1.00, ($base + $integratedAddition) * max(self::MAX_REDUCTION_FACTOR, $factor)));
        $stage1 = $this->stageOneDays($total);
        $stage2 = $this->roundHalfDay(max(0.50, $total - $stage1));
        $surveillance = $this->roundHalfDay(max(1.00, $total / 3));
        $recertification = $this->roundHalfDay(max(1.00, $total * 2 / 3));

        return [
            'total_days' => $total,
            'stage1_days' => $stage1,
            'stage2_days' => $stage2,
            'surveillance1_days' => $surveillance,
            'surveillance2_days' => $surveillance,
            'recertification_days' => $recertification,
            'base_days' => $base,
            'integrated_addition_days' => $this->roundHalfDay($integratedAddition),
            'basis' => $this->basisText($employeeCount, $standardTotals, $standardRules, $factors, $total, $stage1, $stage2, $surveillance, $recertification),
            'employee_count' => $employeeCount,
            'standard_count' => count($standardCodes),
            'standard_days' => $standardTotals,
            'standard_rules' => $standardRules,
            'reduction_percent' => $reductionPercent,
        ];
    }

    public function normalizeStageDays(?float $stage1Days, ?float $stage2Days, array $client, array $standards = []): array
    {
        $calculated = $this->calculateInitialDays($client, $standards);
        $stage1 = $stage1Days !== null && $stage1Days > 0 ? $stage1Days : $calculated['stage1_days'];
        $stage2 = $stage2Days !== null && $stage2Days > 0 ? $stage2Days : $calculated['stage2_days'];

        return [
            'total_days' => $stage1 + $stage2,
            'stage1_days' => $stage1,
            'stage2_days' => $stage2,
            'surveillance1_days' => $calculated['surveillance1_days'] ?? 1.00,
            'surveillance2_days' => $calculated['surveillance2_days'] ?? 1.00,
            'recertification_days' => $calculated['recertification_days'] ?? $stage2,
            'basis' => $calculated['basis'],
        ];
    }

    public function endDateForDuration(DateTimeImmutable $startDate, float $durationDays): DateTimeImmutable
    {
        $wholeWorkingDays = max(1, (int) ceil($durationDays));
        $endDate = $startDate;

        for ($added = 1; $added < $wholeWorkingDays;) {
            $endDate = $endDate->add(new DateInterval('P1D'));

            if ($this->isWorkingDay($endDate)) {
                $added++;
            }
        }

        return $endDate;
    }

    public function addWorkingDays(DateTimeImmutable $date, int $days): DateTimeImmutable
    {
        $current = $date;

        for ($added = 0; $added < $days;) {
            $current = $current->add(new DateInterval('P1D'));

            if ($this->isWorkingDay($current)) {
                $added++;
            }
        }

        return $current;
    }

    private function isWorkingDay(DateTimeImmutable $date): bool
    {
        $day = (int) $date->format('N');

        return ! in_array($day, [5, 6], true);
    }

    private function effectiveEmployeeCount(array $client, array $inputs): int
    {
        $inputCount = (int) ($inputs['effective_employees'] ?? 0);
        if ($inputCount > 0) {
            return $inputCount;
        }

        $clientCount = (int) ($client['employee_count'] ?? 0);
        if ($clientCount > 0) {
            return $clientCount;
        }

        return max(1, (int) ($client['permanent_employees'] ?? 0) + (int) ($client['temporary_employees'] ?? 0));
    }

    private function standardCodes(array $standards, string $fallbackText): array
    {
        $codes = [];
        foreach ($standards as $standard) {
            $code = strtoupper(trim((string) ($standard['standard_code'] ?? $standard['code'] ?? '')));
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        if ($codes !== []) {
            return array_values(array_unique($codes));
        }

        if ($fallbackText !== '') {
            foreach (preg_split('/[,;]+/', $fallbackText) ?: [] as $part) {
                $part = strtoupper(trim($part));
                if ($part !== '') {
                    $codes[] = $part;
                }
            }
        }

        return array_values(array_unique($codes));
    }

    private function schemeTypes(array $standards, array $codes): array
    {
        $schemes = [];
        foreach ($standards as $standard) {
            $code = strtoupper(trim((string) ($standard['standard_code'] ?? $standard['code'] ?? '')));
            if ($code !== '') {
                $schemes[$code] = strtolower((string) ($standard['scheme_type'] ?? ''));
            }
        }

        foreach ($codes as $code) {
            $schemes[$code] ??= '';
        }

        return $schemes;
    }

    private function baseDaysForStandard(string $code, string $scheme, int $employees, array $inputs): float
    {
        return $this->ruleForStandard($code, $scheme, $employees, $inputs)['days'];
    }

    private function ruleForStandard(string $code, string $scheme, int $employees, array $inputs): array
    {
        $upperCode = strtoupper($code);
        $scheme = strtolower($scheme);

        if (str_contains($upperCode, 'HACCP')) {
            return [
                'days' => $this->haccpBaseDays($employees, (int) ($inputs['haccp_plans_processes'] ?? 0)),
                'rule' => 'HACCP food-safety programme rule: employee band + HACCP plan/process complexity.',
                'family' => 'food_safety_haccp',
            ];
        }

        if (str_contains($upperCode, 'ISO 22000') || str_contains($upperCode, 'FSSC') || str_contains($scheme, 'food')) {
            return [
                'days' => $this->iso22000BaseDays($employees, (int) ($inputs['haccp_plans_processes'] ?? 0), (string) ($inputs['food_complexity'] ?? 'medium')),
                'rule' => 'Food-safety management-system rule: employee band + process/category complexity + HACCP plan count.',
                'family' => 'food_safety_management_system',
            ];
        }

        if (str_contains($upperCode, '13485') || str_contains($scheme, 'medical')) {
            return [
                'days' => $this->medicalDeviceBaseDays($employees, (string) ($inputs['medical_complexity'] ?? 'medium')),
                'rule' => 'Medical-device management-system rule: employee band with device-risk complexity uplift.',
                'family' => 'medical_device_management_system',
            ];
        }

        return [
            'days' => $this->managementSystemBaseDays($employees),
            'rule' => 'Management-system rule: effective employee band, adjusted by risk, sites, shifts and approved reductions.',
            'family' => 'management_system',
        ];
    }

    private function managementSystemBaseDays(int $employees): float
    {
        $bands = [
            [5, 1.5],
            [10, 2.0],
            [15, 2.5],
            [25, 3.0],
            [45, 4.0],
            [65, 5.0],
            [85, 6.0],
            [125, 7.0],
            [175, 8.0],
            [275, 9.0],
            [425, 10.0],
            [625, 11.0],
            [875, 12.0],
            [1175, 13.0],
            [1550, 14.0],
            [2025, 15.0],
            [2675, 16.0],
            [3450, 17.0],
            [4350, 18.0],
            [5450, 19.0],
            [6800, 20.0],
            [8500, 21.0],
            [10700, 22.0],
        ];

        foreach ($bands as [$upper, $days]) {
            if ($employees <= $upper) {
                return $days;
            }
        }

        return 22.0 + ceil(($employees - 10700) / 2500);
    }

    private function haccpBaseDays(int $employees, int $haccpPlans): float
    {
        $days = match (true) {
            $employees <= 20 => 2.5,
            $employees <= 50 => 3.0,
            $employees <= 100 => 3.5,
            $employees <= 250 => 4.0,
            $employees <= 500 => 4.5,
            default => 5.0 + ceil(($employees - 500) / 500) * 0.5,
        };

        if ($haccpPlans > 5) {
            $days += min(2.0, ($haccpPlans - 5) * 0.25);
        }

        return $this->roundHalfDay($days);
    }

    private function iso22000BaseDays(int $employees, int $haccpPlans, string $complexity): float
    {
        $days = match (true) {
            $employees <= 20 => 3.0,
            $employees <= 50 => 3.5,
            $employees <= 100 => 4.0,
            $employees <= 250 => 4.5,
            $employees <= 500 => 5.0,
            default => 5.5 + ceil(($employees - 500) / 500) * 0.5,
        };

        $complexityFactor = match (strtolower($complexity)) {
            'high', 'very high' => 0.5,
            'low' => -0.5,
            default => 0.0,
        };

        if ($haccpPlans > 5) {
            $complexityFactor += min(2.0, ($haccpPlans - 5) * 0.25);
        }

        return $this->roundHalfDay(max(2.0, $days + $complexityFactor));
    }

    private function medicalDeviceBaseDays(int $employees, string $complexity): float
    {
        $days = $this->managementSystemBaseDays($employees);
        $uplift = match (strtolower($complexity)) {
            'high', 'class iii', 'implantable', 'sterile' => 1.0,
            'low', 'class i' => 0.0,
            default => 0.5,
        };

        return $this->roundHalfDay($days + $uplift);
    }

    private function stageOneDays(float $total): float
    {
        if ($total <= 3.0) {
            return 1.0;
        }

        return $this->roundHalfDay(min(2.0, max(1.0, $total * 0.25)));
    }

    private function roundHalfDay(float $days): float
    {
        return round($days * 2) / 2;
    }

    private function basisText(int $employees, array $standardTotals, array $standardRules, array $factors, float $total, float $stage1, float $stage2, float $surveillance, float $recertification): string
    {
        $parts = [];
        foreach ($standardTotals as $standard => $days) {
            $rule = $standardRules[$standard]['family'] ?? 'management_system';
            $parts[] = $standard . ': ' . number_format((float) $days, 2) . ' days (' . str_replace('_', ' ', $rule) . ')';
        }

        $ruleNotes = [];
        foreach ($standardRules as $standard => $rule) {
            $ruleNotes[] = $standard . ' - ' . $rule['rule'];
        }

        return self::REFERENCE_NOTE
            . "\nEffective employees: "
            . $employees
            . "\nStandard basis: "
            . implode('; ', $parts)
            . "\nScheme logic: "
            . implode(' ', array_values(array_unique($ruleNotes)))
            . "\nAdjustments: "
            . ($factors === [] ? 'none' : implode('; ', $factors))
            . "\nFormula reference: Initial audit days = round to nearest 0.5 day of [(highest selected standard base days + "
            . (int) (self::MULTI_STANDARD_ADDITION_FACTOR * 100)
            . '% of each additional standard base days) x adjustment factor]. Stage 1 = 1 day when total <= 3 days, otherwise 25% of total capped at 2 days. Stage 2 = total initial audit days - Stage 1. Surveillance = one-third of initial audit days unless scheme rules require more. Recertification = two-thirds of initial audit days unless scheme rules require more.'
            . "\nCalculated result: Initial audit "
            . number_format($total, 2)
            . ' days, split Stage 1 '
            . number_format($stage1, 2)
            . ' / Stage 2 '
            . number_format($stage2, 2)
            . '; Surveillance 1 '
            . number_format($surveillance, 2)
            . '; Surveillance 2 '
            . number_format($surveillance, 2)
            . '; Recertification '
            . number_format($recertification, 2)
            . '.';
    }
}
