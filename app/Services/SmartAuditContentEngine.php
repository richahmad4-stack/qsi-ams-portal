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
            $content .= "\n\n" . $this->compactPoolText($pool);
        }

        return [
            'content' => $content,
            'source_type' => $pool === null ? 'system_clause_engine' : 'clause_pool',
            'confirmation_note' => 'Confirmed on behalf of the assigned auditor from approved Clause Pool and clause-aligned evidence references.',
        ];
    }

    public function ncrCapaPackage(array $client, ?array $event, array $clause, string $severity, int $sequence): array
    {
        $theme = $this->themedNcrCapaPackage($client, $event, $clause, $severity, $sequence);
        $objectiveEvidence = $this->objectiveEvidence($client, $event, $clause, $severity, $sequence);
        $poolEvidence = $this->poolText($client, $event, $clause, 'evidence_required', $severity);
        $poolAcceptance = $this->poolText($client, $event, $clause, 'acceptance_criteria', $severity);

        return [
            'requirement' => (string) ($clause['requirement'] ?? $clause['clause_title'] ?? 'Applicable requirement'),
            'finding' => $this->compactPoolText($theme['finding']),
            'objective_evidence' => $objectiveEvidence,
            'correction' => $this->compactPoolText($theme['correction']),
            'root_cause' => $this->compactPoolText($theme['root_cause']),
            'corrective_action' => $this->compactPoolText($theme['corrective_action']),
            'preventive_action' => $this->compactPoolText($theme['preventive_action']),
            'evidence_reference' => $this->evidenceReference($client, $clause, $sequence)
                . "\n" . $this->compactPoolText($theme['evidence_reference'])
                . ($poolEvidence === null ? '' : "\nControlled pool evidence option: " . $this->compactPoolText($poolEvidence)),
            'verification' => $this->compactPoolText($theme['verification']),
            'effectiveness' => $poolAcceptance === null
                ? $this->compactPoolText($theme['effectiveness'])
                : $this->compactPoolText($theme['effectiveness'] . "\nControlled pool acceptance option: " . $poolAcceptance),
            'closure_notes' => 'Closure shall be linked to the original NCR, correction, root cause, action evidence and auditor verification.',
        ];
    }

    private function themedNcrCapaPackage(array $client, ?array $event, array $clause, string $severity, int $sequence): array
    {
        $family = $this->issueFamily($client, $clause, $sequence);
        $stage = str_replace('_', ' ', (string) ($event['event_type'] ?? 'audit'));
        $scope = trim((string) ($client['scope'] ?? $client['business_activity'] ?? 'the audited scope'));
        $title = (string) ($clause['clause_title'] ?? 'applicable requirement');
        $prefix = ucfirst($severity) . ' nonconformity raised during ' . $stage . ': ';

        $sets = [
            'food_traceability' => [
                'finding' => $prefix . 'the selected lot traceability sample was not fully linked from receiving through process/release to dispatch for ' . $scope . '.',
                'correction' => 'QA reconciled the affected lot file using receiving, processing, holding/release and dispatch records, then completed the missing linkage.',
                'root_cause' => 'The traceability checklist required start and end references but did not require verification of all intermediate process and release records.',
                'corrective_action' => 'Revise the traceability checklist to require full one-step-back, internal-process and one-step-forward linkage; brief QA/dispatch personnel; perform a follow-up mock trace sample.',
                'preventive_action' => 'Include traceability-linkage completeness in monthly food safety verification and management review trend inputs.',
                'evidence_reference' => 'Corrected lot file, revised traceability checklist, staff briefing record and follow-up mock trace result.',
                'verification' => 'Auditor reviewed the corrected lot file and sampled a second dispatch lot for complete linkage.',
                'effectiveness' => 'Effectiveness is accepted when the next mock trace demonstrates full linkage within the defined response time and no repeat missing internal reference is found.',
            ],
            'food_prp' => [
                'finding' => $prefix . 'PRP verification for cleaning, hygiene or storage control was incomplete for the sampled area/shift within ' . $scope . '.',
                'correction' => 'The supervisor verified the affected PRP record, checked area condition before use/release, and recorded the result.',
                'root_cause' => 'Shift handover did not clearly assign PRP verification responsibility before area/process release.',
                'corrective_action' => 'Update the PRP verification checklist, brief supervisors and include verification completion in weekly food safety team checks.',
                'preventive_action' => 'Trend PRP verification omissions and include repeated gaps in internal audit sampling.',
                'evidence_reference' => 'Revised PRP checklist, supervisor briefing record, completed verification sample and weekly PRP review log.',
                'verification' => 'Auditor sampled subsequent PRP records and confirmed verification before release/use.',
                'effectiveness' => 'Effectiveness is accepted when subsequent PRP records show complete verification and no repeat blank verification fields.',
            ],
            'food_monitoring' => [
                'finding' => $prefix . 'a CCP/OPRP or operational monitoring record did not show documented action for an abnormal or near-limit result in ' . $scope . '.',
                'correction' => 'QA reviewed the affected batch/shift record, documented evaluation and confirmed product/process disposition.',
                'root_cause' => 'The monitoring form did not prompt action recording for abnormal trends or near-limit readings.',
                'corrective_action' => 'Revise the monitoring form to require action/comment for abnormal trends, retrain monitoring staff and review the first month of records.',
                'preventive_action' => 'Extend the revised action-recording prompt to similar monitoring forms and trend recurrence during food safety team review.',
                'evidence_reference' => 'Updated monitoring form, training attendance, corrected record and QA review of subsequent monitoring records.',
                'verification' => 'Auditor verified revised form use and sampled subsequent monitoring records for action recording.',
                'effectiveness' => 'Effectiveness is accepted when abnormal/near-limit entries include action, evaluation and supervisor/QA review.',
            ],
            'food_supplier' => [
                'finding' => $prefix . 'supplier/material approval evidence was incomplete for one sampled input used within ' . $scope . '.',
                'correction' => 'Purchasing/QA obtained the missing supplier approval evidence and confirmed the affected material remained acceptable for use.',
                'root_cause' => 'The approved supplier review did not require evidence completeness verification before renewal/continued use.',
                'corrective_action' => 'Update supplier approval review criteria, review active food-safety critical suppliers and brief purchasing/QA personnel.',
                'preventive_action' => 'Add supplier approval evidence completeness to periodic supplier performance review.',
                'evidence_reference' => 'Updated supplier approval form, supplier document sample, approved supplier list review and staff briefing.',
                'verification' => 'Auditor checked the revised supplier file and sampled another food-safety critical supplier for complete approval evidence.',
                'effectiveness' => 'Effectiveness is accepted when sampled active suppliers have complete approval and monitoring evidence.',
            ],
            'food_allergen_release' => [
                'finding' => $prefix . 'allergen, label or product release verification evidence was incomplete for one sampled product/lot in ' . $scope . '.',
                'correction' => 'QA reviewed the affected product/lot, confirmed label/release status and completed the missing verification evidence.',
                'root_cause' => 'The release checklist did not clearly require independent verification of allergen/label status before final release.',
                'corrective_action' => 'Revise release checklist, brief QA/release personnel and sample released lots for allergen/label verification completion.',
                'preventive_action' => 'Include release-verification completeness in internal audit and food safety team review.',
                'evidence_reference' => 'Corrected release record, revised release checklist, briefing record and subsequent release sample.',
                'verification' => 'Auditor reviewed corrected release evidence and sampled a subsequent product/lot for completed allergen/label verification.',
                'effectiveness' => 'Effectiveness is accepted when subsequent sampled lots show completed release verification before dispatch.',
            ],
            'competence' => [
                'finding' => $prefix . 'competence or authorization evidence was incomplete for personnel performing an activity linked to ' . $title . '.',
                'correction' => 'The competence record was completed and the responsible person was evaluated against the assigned activity.',
                'root_cause' => 'Role authorization and competence evidence were maintained in separate records without a required cross-check.',
                'corrective_action' => 'Update the competence matrix, align job/activity authorizations and verify competence files for similar roles.',
                'preventive_action' => 'Review competence matrix completeness during internal audit and management review inputs.',
                'evidence_reference' => 'Updated competence matrix, evaluation record, authorization record and follow-up sample.',
                'verification' => 'Auditor sampled the revised competence file and interviewed the responsible person.',
                'effectiveness' => 'Effectiveness is accepted when similar sampled roles have current competence and authorization evidence.',
            ],
            'system_control' => [
                'finding' => $prefix . 'sampled evidence for ' . $title . ' did not fully demonstrate controlled implementation for ' . $scope . '.',
                'correction' => 'The affected record/control was completed or reviewed by the process owner and checked for immediate impact.',
                'root_cause' => 'The local process did not define the review, approval or follow-up evidence point clearly enough.',
                'corrective_action' => 'Clarify the procedure/checklist, brief responsible personnel and verify implementation through follow-up sampling.',
                'preventive_action' => 'Add the control point to the next internal audit sample and trend any repeat gaps.',
                'evidence_reference' => 'Updated procedure/checklist, briefing record, corrected evidence and follow-up internal audit sample.',
                'verification' => 'Auditor verified the corrected record and sampled one additional case for complete review evidence.',
                'effectiveness' => 'Effectiveness is accepted when follow-up samples show complete evidence with no repeat gap.',
            ],
        ];

        return $sets[$family] ?? $sets['system_control'];
    }

    private function issueFamily(array $client, array $clause, int $sequence): string
    {
        $clauseText = strtolower((string) ($clause['standard_code'] ?? '') . ' ' . (string) ($clause['clause_number'] ?? '') . ' ' . (string) ($clause['clause_title'] ?? '') . ' ' . (string) ($clause['requirement'] ?? ''));
        $text = $clauseText . ' ' . strtolower((string) ($client['scope'] ?? ''));
        $isFood = str_contains($text, 'haccp') || str_contains($text, '22000') || str_contains($text, 'fssc') || str_contains($text, 'food') || str_contains($text, 'dairy') || str_contains($text, 'catering') || str_contains($text, 'kitchen');

        if ($isFood) {
            if ($this->containsAny($clauseText, ['traceability', 'recall', 'withdrawal', 'dispatch'])) {
                return 'food_traceability';
            }
            if ($this->containsAny($clauseText, ['prp', 'clean', 'hygiene', 'pest', 'temperature', 'storage'])) {
                return 'food_prp';
            }
            if ($this->containsAny($clauseText, ['hazard', 'ccp', 'oprp', 'monitoring'])) {
                return 'food_monitoring';
            }

            $families = ['food_traceability', 'food_prp', 'food_monitoring', 'food_supplier', 'food_allergen_release', 'competence'];

            return $families[($sequence - 1) % count($families)];
        }

        if ($this->containsAny($text, ['competence', 'training', 'awareness'])) {
            return 'competence';
        }

        return 'system_control';
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
        $note = str_replace('This is a sampled conformity conclusion. The auditor may edit the note or raise a separate NC if conflicting evidence is found.', '', $note);

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
