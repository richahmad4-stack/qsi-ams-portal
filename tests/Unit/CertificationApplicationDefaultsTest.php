<?php

namespace Tests\Unit;

use App\Services\CertificationApplicationDefaults;
use PHPUnit\Framework\TestCase;

class CertificationApplicationDefaultsTest extends TestCase
{
    public function testHaccpCateringAnswersFollowTheRequestedScope(): void
    {
        $defaults = new CertificationApplicationDefaults();
        $client = ['scope' => 'Preparation and delivery of chilled and hot meals for hospitals, offices and industrial camps.'];
        $standards = [['standard_code' => 'HACCP']];

        $legal = (string) $defaults->applicationAnswer('legal_statutory_requirements', $client, $standards);
        $processes = (string) $defaults->applicationAnswer('processes', $client, $standards);

        self::assertStringContainsString('Codex Alimentarius HACCP', $legal);
        self::assertStringContainsString('chilled and hot meals', strtolower($legal));
        self::assertStringContainsString('cooking or hot holding', strtolower($processes));
        self::assertSame('2', $defaults->applicationAnswer('haccp_plans_processes', $client, $standards));
        self::assertStringNotContainsString('bakery', strtolower($legal . ' ' . $processes));
    }

    public function testIso22000DairyAnswersDoNotImportUnselectedStandards(): void
    {
        $defaults = new CertificationApplicationDefaults();
        $client = ['scope' => 'Receiving, pasteurization, filling, cold storage and dispatch of dairy products.'];
        $standards = [['standard_code' => 'ISO 22000:2018']];

        $legal = (string) $defaults->applicationAnswer('legal_statutory_requirements', $client, $standards);
        $products = (string) $defaults->applicationAnswer('products', $client, $standards);
        $processes = (string) $defaults->applicationAnswer('processes', $client, $standards);

        self::assertStringContainsString('ISO 22000:2018', $legal);
        self::assertStringNotContainsString('Codex Alimentarius HACCP', $legal);
        self::assertStringContainsString('dairy products', strtolower($products));
        self::assertStringContainsString('pasteurization', strtolower($processes));
        self::assertStringNotContainsString('ISO 9001', $legal . $products . $processes);
        self::assertStringNotContainsString('ISO 45001', $legal . $products . $processes);
    }

    public function testNonFoodStandardDoesNotReceiveFoodSafetyQuestions(): void
    {
        $defaults = new CertificationApplicationDefaults();

        self::assertNull($defaults->applicationAnswer(
            'legal_statutory_requirements',
            ['scope' => 'Manufacture of metal components.'],
            [['standard_code' => 'ISO 9001:2015']]
        ));
    }
}
