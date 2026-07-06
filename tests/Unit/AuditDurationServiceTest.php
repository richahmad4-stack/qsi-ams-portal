<?php

namespace Tests\Unit;

use App\Services\AuditDurationService;
use PHPUnit\Framework\TestCase;

class AuditDurationServiceTest extends TestCase
{
    public function testMultiStandardFoodAndManagementCalculationKeepsSeparateBasis(): void
    {
        $service = new AuditDurationService();

        $result = $service->calculateApplicationReview(
            ['employee_count' => 30, 'number_of_sites' => 1],
            [
                ['standard_code' => 'HACCP', 'scheme_type' => 'food_safety'],
                ['standard_code' => 'ISO 9001:2015', 'scheme_type' => 'management_system'],
            ],
            ['haccp_plans_processes' => 2]
        );

        self::assertGreaterThan(0, $result['total_days']);
        self::assertArrayHasKey('HACCP', $result['standard_days']);
        self::assertArrayHasKey('ISO 9001:2015', $result['standard_days']);
        self::assertStringContainsString('Controlled QSI audit-duration rule set', $result['basis']);
        self::assertStringContainsString('HACCP', $result['basis']);
        self::assertStringContainsString('ISO 9001:2015', $result['basis']);
    }

    public function testStageOneAndStageTwoSplitRemainsBalanced(): void
    {
        $service = new AuditDurationService();

        $result = $service->calculateApplicationReview(
            ['employee_count' => 80, 'number_of_sites' => 2],
            [['standard_code' => 'ISO 22000:2018', 'scheme_type' => 'food_safety']],
            ['risk_classification' => 'high', 'haccp_plans_processes' => 8]
        );

        self::assertEqualsWithDelta($result['total_days'], $result['stage1_days'] + $result['stage2_days'], 0.01);
        self::assertGreaterThanOrEqual(1.0, $result['stage1_days']);
        self::assertGreaterThanOrEqual(0.5, $result['stage2_days']);
        self::assertGreaterThanOrEqual(1.0, $result['surveillance1_days']);
        self::assertGreaterThanOrEqual(1.0, $result['recertification_days']);
    }
}
