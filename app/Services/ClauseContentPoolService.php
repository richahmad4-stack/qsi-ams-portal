<?php

namespace App\Services;

use Config\Database;

class ClauseContentPoolService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function conformityNote(array $client, ?array $event, array $clause): ?string
    {
        $template = $this->bestTemplate($client, $event, $clause, 'conformity_answer');

        return $template === null ? null : $this->fillTemplate($template, $client, $event, $clause);
    }

    public function templateFor(array $client, ?array $event, array $clause, string $contentType, ?string $severity = null): ?array
    {
        return $this->bestTemplate($client, $event, $clause, $contentType, $severity);
    }

    private function bestTemplate(array $client, ?array $event, array $clause, string $contentType, ?string $severity = null): ?array
    {
        if (! in_array('clause_content_pool', $this->db->listTables(), true)) {
            return null;
        }

        $tenantId = (int) ($client['tenant_id'] ?? session()->get('tenant_id') ?? 0);
        $stage = (string) ($event['event_type'] ?? '');
        $scope = strtolower((string) ($client['scope'] ?? $client['business_activity'] ?? ''));
        $standardId = (int) ($clause['standard_id'] ?? 0);
        $clauseId = (int) ($clause['id'] ?? 0);

        $rows = $this->db->table('clause_content_pool')
            ->where('tenant_id', $tenantId)
            ->where('content_type', $contentType)
            ->where('active', 1)
            ->groupStart()
                ->where('standard_id', $standardId)
                ->orWhere('standard_id', null)
            ->groupEnd()
            ->groupStart()
                ->where('clause_library_id', $clauseId)
                ->orWhere('clause_library_id', null)
            ->groupEnd()
            ->groupStart()
                ->where('audit_stage', $stage)
                ->orWhere('audit_stage', 'all')
                ->orWhere('audit_stage', null)
            ->groupEnd()
            ->get()
            ->getResultArray();

        $best = null;
        $bestScore = -1;
        foreach ($rows as $row) {
            if ($severity !== null && ($row['severity'] ?? '') !== '' && $row['severity'] !== $severity) {
                continue;
            }

            $score = 0;
            if ((int) ($row['clause_library_id'] ?? 0) === $clauseId) {
                $score += 40;
            }
            if ((int) ($row['standard_id'] ?? 0) === $standardId) {
                $score += 20;
            }
            if (($row['audit_stage'] ?? '') === $stage) {
                $score += 10;
            }
            if ($this->scopeMatches((string) ($row['scope_keyword'] ?? ''), $scope)) {
                $score += 30;
            }
            if ($this->categoryMatches($row, $client)) {
                $score += 15;
            }

            if ($score > $bestScore) {
                $best = $row;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function scopeMatches(string $keyword, string $scope): bool
    {
        $keyword = strtolower(trim($keyword));
        if ($keyword === '' || $keyword === 'all') {
            return true;
        }

        foreach (preg_split('/[,;|]+/', $keyword) ?: [] as $part) {
            $part = trim($part);
            if ($part !== '' && str_contains($scope, $part)) {
                return true;
            }
        }

        return false;
    }

    private function categoryMatches(array $row, array $client): bool
    {
        foreach (['iaf_code_id', 'food_chain_category_id', 'medical_device_category_id'] as $field) {
            $required = (int) ($client[$field] ?? 0);
            $template = (int) ($row[$field] ?? 0);
            if ($template > 0 && $required > 0 && $template !== $required) {
                return false;
            }
        }

        return true;
    }

    private function fillTemplate(array $template, array $client, ?array $event, array $clause): string
    {
        $text = (string) ($template['content_text'] ?? '');
        $tokens = [
            '{client}' => (string) ($client['company'] ?? $client['client_name'] ?? 'the client'),
            '{scope}' => (string) ($client['scope'] ?? $client['business_activity'] ?? 'the certified scope'),
            '{stage}' => ucwords(str_replace('_', ' ', (string) ($event['event_type'] ?? 'audit'))),
            '{standard}' => (string) ($clause['standard_code'] ?? 'standard'),
            '{clause}' => (string) ($clause['clause_number'] ?? ''),
            '{title}' => (string) ($clause['clause_title'] ?? ''),
            '{reference}' => $this->reference($client, $clause, (int) ($template['id'] ?? 0)),
        ];

        return strtr($text, $tokens) . "\n\nTemplate reference: CP-" . (int) ($template['id'] ?? 0) . '. Generated from Clause Pool; editable by auditor.';
    }

    private function reference(array $client, array $clause, int $templateId): string
    {
        $letters = strtoupper(preg_replace('/[^A-Z]/', '', (string) ($client['company'] ?? $client['client_name'] ?? 'QSI')));

        return substr($letters . 'QSI', 0, 3) . '-' . ($clause['clause_number'] ?? 'GEN') . '-CP-' . str_pad((string) $templateId, 3, '0', STR_PAD_LEFT);
    }
}
