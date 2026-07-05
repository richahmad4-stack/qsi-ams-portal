<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDetailedProposalContractFields extends Migration
{
    public function up(): void
    {
        if (! $this->columnExists('proposals', 'proposal_date')) {
            $this->db->query(
                'ALTER TABLE proposals
                    ADD proposal_date DATE NULL AFTER status,
                    ADD client_reference VARCHAR(120) NULL AFTER proposal_date,
                    ADD proposal_payload JSON NULL AFTER currency'
            );
        }

        if (! $this->columnExists('contracts', 'document_number')) {
            $this->db->query(
                "ALTER TABLE contracts
                    ADD document_number VARCHAR(40) NOT NULL DEFAULT 'F 27' AFTER contract_number,
                    ADD revision_number VARCHAR(20) NOT NULL DEFAULT '2' AFTER document_number,
                    ADD issue_number VARCHAR(20) NOT NULL DEFAULT '2' AFTER revision_number,
                    ADD document_date DATE NOT NULL DEFAULT '2022-05-15' AFTER issue_number,
                    ADD contract_payload JSON NULL AFTER signed_by_name,
                    ADD qsi_signatory_name VARCHAR(180) NULL AFTER contract_payload,
                    ADD qsi_signatory_date DATE NULL AFTER qsi_signatory_name,
                    ADD client_signatory_name VARCHAR(180) NULL AFTER qsi_signatory_date,
                    ADD client_signatory_date DATE NULL AFTER client_signatory_name"
            );
        }
    }

    public function down(): void
    {
        if ($this->columnExists('contracts', 'document_number')) {
            $this->db->query(
                'ALTER TABLE contracts
                    DROP COLUMN client_signatory_date,
                    DROP COLUMN client_signatory_name,
                    DROP COLUMN qsi_signatory_date,
                    DROP COLUMN qsi_signatory_name,
                    DROP COLUMN contract_payload,
                    DROP COLUMN document_date,
                    DROP COLUMN issue_number,
                    DROP COLUMN revision_number,
                    DROP COLUMN document_number'
            );
        }

        if ($this->columnExists('proposals', 'proposal_date')) {
            $this->db->query(
                'ALTER TABLE proposals
                    DROP COLUMN proposal_payload,
                    DROP COLUMN client_reference,
                    DROP COLUMN proposal_date'
            );
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        return $this->db->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $this->db->escape($column))
            ->getRowArray() !== null;
    }
}
