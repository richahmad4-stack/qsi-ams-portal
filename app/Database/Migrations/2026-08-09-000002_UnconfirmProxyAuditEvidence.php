<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UnconfirmProxyAuditEvidence extends Migration
{
    public function up(): void
    {
        if (! $this->tableExists('report_sections') || ! $this->db->fieldExists('auditor_confirmed', 'report_sections')) {
            return;
        }

        $proxyNotes = [
            'Legacy report section marked confirmed during migration. New generated sections require explicit auditor confirmation.',
            'Confirmed by assigned auditor from the prepared cycle file and clause-aligned evidence trail.',
            'Auto-confirmed on behalf of the assigned auditor from approved Clause Pool / system content.',
            'Confirmed on behalf of the assigned auditor',
        ];

        $this->db->table('report_sections')
            ->where('section_key', 'conformity')
            ->whereIn('confirmation_note', $proxyNotes)
            ->update([
                'auditor_confirmed' => 0,
                'confirmed_by_user_id' => null,
                'confirmed_at' => null,
                'confirmation_note' => null,
            ]);
    }

    public function down(): void
    {
        // Proxy confirmations are intentionally not recreated.
    }

    private function tableExists(string $table): bool
    {
        return in_array($table, $this->db->listTables(), true);
    }
}
