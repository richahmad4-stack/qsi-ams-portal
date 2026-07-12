<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ClientPortalProductionWiringTest extends TestCase
{
    private string $controller;

    protected function setUp(): void
    {
        $this->controller = file_get_contents(__DIR__ . '/../../app/Controllers/ClientPortalController.php') ?: '';
    }

    public function testClientPortalDownloadsAreOwnershipAndReadinessGated(): void
    {
        self::assertStringContainsString('canAccessClient($clientId)', $this->controller);
        self::assertStringContainsString('clientDocumentAvailable($clientId, $documentKey)', $this->controller);
        self::assertStringContainsString('eventForClientUser($eventId)', $this->controller);
        self::assertStringContainsString('eventDocumentAvailable($eventId, $documentKey)', $this->controller);
        self::assertStringContainsString('certificateForClientUser($certificateId)', $this->controller);
        self::assertStringContainsString('capaForClientUser($capaId)', $this->controller);
    }

    public function testClientPortalSubmissionsAreOwnershipAndStateGated(): void
    {
        self::assertStringContainsString("['closed', 'verified_closed', 'cancelled']", $this->controller);
        self::assertStringContainsString('This CAPA is already closed', $this->controller);
        self::assertStringContainsString('$certificateId = $this->intOrNull(\'certificate_id\')', $this->controller);
        self::assertStringContainsString('$this->certificateForClientUser($certificateId) === null', $this->controller);
        self::assertStringContainsString('Selected certificate is not available for this client.', $this->controller);
    }

    public function testClientPortalOnlyShowsReadyDocumentsAsDownloadButtons(): void
    {
        $index = file_get_contents(__DIR__ . '/../../app/Views/client_portal/index.php') ?: '';
        $eventTable = file_get_contents(__DIR__ . '/../../app/Views/client_portal/_event_table.php') ?: '';

        self::assertStringContainsString("\$document['available']", $index);
        self::assertStringContainsString('Not ready yet', $this->controller);
        self::assertStringContainsString('type="button" disabled>Pending</button>', $index);
        self::assertStringContainsString("\$document['available']", $eventTable);
        self::assertStringContainsString('type="button" disabled', $eventTable);
    }

    public function testClientPortalUsesControlledDocumentReadinessRules(): void
    {
        foreach ([
            'certification_applications',
            'proposals',
            'contracts',
            'auditor_appointments',
            'audit_plans',
            'report_drafts',
        ] as $table) {
            self::assertStringContainsString($table, $this->controller);
        }

        self::assertStringContainsString("'submitted', 'approved', 'completed'", $this->controller);
        self::assertStringContainsString("'sent', 'accepted', 'approved', 'completed'", $this->controller);
        self::assertStringContainsString("'sent', 'signed', 'active', 'approved', 'completed'", $this->controller);
        self::assertStringContainsString('greater_than_equal_to[1]|less_than_equal_to[5]', $this->controller);
    }
}
