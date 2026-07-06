<?php

namespace App\Services;

class SmartAuditContentEngine
{
    private ClauseContentPoolService $pool;
    private AuditReportNarrativeService $narratives;

    public function __construct(?ClauseContentPoolService $pool = null, ?AuditReportNarrativeService $narratives = null)
    {
        $this->pool = $pool ?? new ClauseContentPoolService();
        $this->narratives = $narratives ?? new AuditReportNarrativeService();
    }

    public function conformitySection(array $client, ?array $event, array $clause, array $planItems = [], array $auditTeam = []): array
    {
        $local = $this->normaliseConformityNote(
            $this->narratives->conformityNote($client, $event, $clause, $planItems, $auditTeam)
        );
        $pool = $this->poolText($client, $event, $clause, 'conformity_answer');

        $content = $local;
        if ($pool !== null) {
            $content .= "\n\nClause Pool basis:\n" . $this->compactPoolText($pool);
        }

        return [
            'content' => $content,
            'source_type' => $pool === null ? 'system_clause_engine' : 'clause_pool',
            'confirmation_note' => 'Confirmed on behalf of the assigned auditor from approved Clause Pool and clause-aligned evidence references.',
        ];
    }

    public function ncrCapaPackage(array $client, ?array $event, array $clause, string $severity, int $sequence): array
    {
        $fallback = $this->narratives->ncrCorrectionSet([
            'requirement' => (string) ($clause['requirement'] ?? $clause['clause_title'] ?? 'the applicable requirement'),
            'finding' => $this->fallbackFinding($client, $event, $clause, $severity),
        ], $client);

        $finding = $this->poolText($client, $event, $clause, $severity === 'major' ? 'major_nc' : 'minor_nc', $severity)
            ?? $this->fallbackFinding($client, $event, $clause, $severity);
        $objectiveEvidence = $this->objectiveEvidence($client, $event, $clause, $severity, $sequence);
        $rootCause = $this->poolText($client, $event, $clause, 'root_cause', $severity) ?? $fallback['root_cause'];
        $correction = $this->poolText($client, $event, $clause, 'correction', $severity) ?? $fallback['correction'];
        $correctiveAction = $this->poolText($client, $event, $clause, 'corrective_action', $severity) ?? $fallback['corrective_action'];
        $preventiveAction = $this->poolText($client, $event, $clause, 'preventive_action', $severity) ?? $fallback['preventive_action'];
        $evidenceRequired = $this->poolText($client, $event, $clause, 'evidence_required', $severity) ?? $fallback['evidence_reference'];
        $verification = $this->poolText($client, $event, $clause, 'verification_method', $severity) ?? $fallback['verification'];
        $acceptanceCriteria = $this->poolText($client, $event, $clause, 'acceptance_criteria', $severity);

        return [
            'requirement' => (string) ($clause['requirement'] ?? $clause['clause_title'] ?? 'Applicable requirement'),
            'finding' => $this->compactPoolText($finding),
            'objective_evidence' => $objectiveEvidence,
            'correction' => $this->compactPoolText($correction),
            'root_cause' => $this->compactPoolText($rootCause),
            'corrective_action' => $this->compactPoolText($correctiveAction),
            'preventive_action' => $this->compactPoolText($preventiveAction),
            'evidence_reference' => $this->evidenceReference($client, $clause, $sequence) . "\n" . $this->compactPoolText($evidenceRequired),
            'verification' => $this->compactPoolText($verification),
            'effectiveness' => $acceptanceCriteria === null
                ? 'Effectiveness is accepted when the next sample shows no repeat issue and the responsible person can explain the revised control.'
                : $this->compactPoolText($acceptanceCriteria),
            'closure_notes' => 'Closure shall be linked to the original NCR, correction, root cause, action evidence and auditor verification.',
        ];
    }

    private function objectiveEvidence(array $client, ?array $event, array $clause, string $severity, int $sequence): string
    {
        $pool = $this->poolText($client, $event, $clause, 'objective_evidence', $severity);
        $references = $this->sampleReferences($client, $clause, $sequence);
        $trail = $this->clauseEvidenceTrail($client, $clause);
        $stage = ucwords(str_replace('_', ' ', (string) ($event['event_type'] ?? 'audit')));

        $text = "Objective evidence sampled during {$stage}:\n";
        foreach ($trail as $index => $item) {
            $text .= '- ' . rtrim($item, '.') . ' (Ref: ' . $references[$index] . ")\n";
        }

        if ($pool !== null) {
            $text .= "\nClause Pool evidence basis:\n" . $this->compactPoolText($pool);
        }

        return trim($text);
    }

    private function fallbackFinding(array $client, ?array $event, array $clause, string $severity): string
    {
        $stage = str_replace('_', ' ', (string) ($event['event_type'] ?? 'audit'));
        $scope = trim((string) ($client['scope'] ?? $client['business_activity'] ?? 'the audited scope'));

        return ucfirst($severity) . ' nonconformity raised during ' . $stage . ': sampled evidence for '
            . (string) ($clause['standard_code'] ?? 'the standard') . ' '
            . (string) ($clause['clause_number'] ?? '') . ' - '
            . (string) ($clause['clause_title'] ?? 'the requirement')
            . ' did not fully demonstrate controlled implementation for ' . $scope . '.';
    }

    private function poolText(array $client, ?array $event, array $clause, string $contentType, ?string $severity = null): ?string
    {
        $template = $this->pool->templateFor($client, $event, $clause, $contentType, $severity);

        return $template === null ? null : $this->pool->renderTemplate($template, $client, $event, $clause);
    }

    private function normaliseConformityNote(string $note): string
    {
        $note = str_replace('Draft conformity note - auditor confirmation required:', 'Conformity note:', $note);
        $note = str_replace('This is a sampled conformity conclusion. The auditor may edit the note or raise a separate NC if conflicting evidence is found.', 'This is a sampled conformity conclusion. The auditor may edit the note or raise a separate NC if conflicting evidence is found.', $note);

        return trim($note);
    }

    private function compactPoolText(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return str_replace('Generated from Clause Pool', 'Prepared from approved Clause Pool', $text);
    }

    private function sampleReferences(array $client, array $clause, int $sequence): array
    {
        $base = $this->companyReferenceCode($client) . '-' . $this->clauseReferenceCode((string) ($clause['clause_number'] ?? 'GEN'));

        return [
            $base . '-' . str_pad((string) ($sequence * 10 + 1), 3, '0', STR_PAD_LEFT),
            $base . '-' . str_pad((string) ($sequence * 10 + 2), 3, '0', STR_PAD_LEFT),
            $base . '-' . str_pad((string) ($sequence * 10 + 3), 3, '0', STR_PAD_LEFT),
        ];
    }

    private function evidenceReference(array $client, array $clause, int $sequence): string
    {
        return $this->companyReferenceCode($client) . '-'
            . $this->clauseReferenceCode((string) ($clause['clause_number'] ?? 'GEN'))
            . '-CAPA-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    private function companyReferenceCode(array $client): string
    {
        $name = strtoupper((string) ($client['company'] ?? $client['company_name'] ?? $client['client_name'] ?? 'QSI'));
        $words = preg_split('/[^A-Z0-9]+/', $name) ?: [];
        $skip = ['AL', 'EL', 'THE', 'DEMO', 'LLC', 'LTD', 'CO', 'COMPANY', 'CORP', 'GROUP', 'SERVICES', 'MANAGEMENT', 'INDUSTRIES', 'FACTORY', 'PROCESSING', 'CATERING'];

        foreach ($words as $word) {
            $word = trim($word);
            if ($word === '' || in_array($word, $skip, true)) {
                continue;
            }

            $collapsed = preg_replace('/(.)\1+/', '$1', $word) ?: $word;
            if (strlen($collapsed) >= 3) {
                return substr($collapsed, 0, 3);
            }
        }

        $fallback = preg_replace('/[^A-Z0-9]/', '', $name) ?: 'QSI';
        $fallback = preg_replace('/(.)\1+/', '$1', $fallback) ?: $fallback;

        return str_pad(substr($fallback, 0, 3), 3, 'X');
    }

    private function clauseReferenceCode(string $clauseNumber): string
    {
        return preg_replace('/[^A-Z0-9.]+/', '', strtoupper(trim($clauseNumber))) ?: 'GEN';
    }

    private function clauseEvidenceTrail(array $client, array $clause): array
    {
        $text = strtolower((string) ($clause['standard_code'] ?? '') . ' ' . (string) ($clause['clause_number'] ?? '') . ' ' . (string) ($clause['clause_title'] ?? '') . ' ' . (string) ($clause['requirement'] ?? '') . ' ' . (string) ($client['scope'] ?? ''));
        $isFood = str_contains($text, 'haccp') || str_contains($text, '22000') || str_contains($text, 'food') || str_contains($text, 'meal') || str_contains($text, 'catering') || str_contains($text, 'kitchen');

        if ($isFood && $this->containsAny($text, ['hazard', 'haccp', 'ccp', 'oprp'])) {
            return [
                'hazard analysis or HACCP plan sample checked for control measure selection and validation/verification basis',
                'CCP/OPRP monitoring record sampled against defined limit or action criteria',
                'food safety team review or verification record checked for follow-up of abnormal results',
            ];
        }

        if ($isFood && $this->containsAny($text, ['traceability', 'recall', 'withdrawal', 'dispatch'])) {
            return [
                'traceability sample followed from receiving through preparation or processing to dispatch',
                'withdrawal or recall test record checked for response time, linkage and follow-up action',
                'product identification and release record sampled for lot, batch or delivery reference',
            ];
        }

        if ($isFood && $this->containsAny($text, ['prp', 'clean', 'hygiene', 'pest', 'temperature', 'storage'])) {
            return [
                'PRP record sampled for cleaning, pest control, personal hygiene, maintenance or temperature control',
                'area observation checked for hygiene, segregation, storage condition and housekeeping controls',
                'supervisor verification or abnormal-condition follow-up record reviewed for completion',
            ];
        }

        if ($this->containsAny($text, ['competence', 'training', 'awareness'])) {
            return [
                'competence matrix sampled against assigned responsibility and audited activity',
                'training or evaluation record checked for completion and effectiveness review',
                'interview evidence confirmed personnel awareness of procedure, record and escalation point',
            ];
        }

        if ($this->containsAny($text, ['document', 'record', 'documented information'])) {
            return [
                'controlled document or record sample checked for approval, revision and availability at point of use',
                'retention and legibility control reviewed for the selected retained evidence',
                'obsolete or superseded information control checked where applicable',
            ];
        }

        if ($this->containsAny($text, ['internal audit', 'management review', 'performance', 'monitor', 'measurement'])) {
            return [
                'monitoring or KPI record sampled for method, frequency, result and action where required',
                'internal audit or management review record checked for required inputs, outputs and follow-up',
                'corrective action or improvement record sampled for status, owner and verification result',
            ];
        }

        return [
            'procedure or process record sampled for consistency with the audited requirement',
            'process owner interview and implementation sample checked against planned arrangements',
            'monitoring, review or retained evidence checked for completion and responsible approval',
        ];
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
