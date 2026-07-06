<?php

namespace App\Services;

class AuditReportNarrativeService
{
    public function conformityNote(array $client, ?array $event, array $clause, array $planItems = [], array $auditTeam = []): string
    {
        $standard = strtoupper(trim((string) ($clause['standard_code'] ?? 'Standard')));
        $clauseNumber = trim((string) ($clause['clause_number'] ?? ''));
        $clauseTitle = trim((string) ($clause['clause_title'] ?? 'applicable requirement'));
        $scope = rtrim(trim((string) ($client['scope'] ?? $client['business_activity'] ?? 'the certified activities')), '.');
        $stage = $this->stageLabel((string) ($event['event_type'] ?? 'audit'));
        $processes = $this->processTrail($planItems, $standard, $clauseTitle, $scope);
        $evidence = $this->evidenceTrail($standard, $clauseTitle, (string) ($clause['evidence_examples'] ?? ''), $scope);
        $auditNumber = trim((string) ($event['audit_number'] ?? ''));
        $sampledEvidence = array_slice($evidence, 0, 3);
        $clauseFocus = $this->clauseFocus($clauseNumber, $clauseTitle);

        return trim(
            "Conformity note:\n"
            . "Sampled during {$stage}" . ($auditNumber !== '' ? " ({$auditNumber})" : '') . " for {$standard} {$clauseNumber} - {$clauseTitle}. "
            . "The audit trail covered {$processes} within the scope \"{$scope}\". "
            . "Evidence reviewed was generally consistent with the requirement for {$clauseFocus}; no NC was raised from this sample.\n\n"
            . "Objective evidence sampled:\n"
            . $this->bulletList($sampledEvidence)
            . "\nAuditor remark:\n"
            . "This is a sampled conformity conclusion. The auditor may edit the note or raise a separate NC if conflicting evidence is found."
        );
    }

    public function ncrCorrectionSet(array $ncr, array $client = []): array
    {
        $requirement = trim((string) ($ncr['requirement'] ?? 'the applicable requirement'));
        $finding = trim((string) ($ncr['finding'] ?? 'the nonconformity'));
        $scope = trim((string) ($client['scope'] ?? 'the audited process'));
        $text = strtolower($requirement . ' ' . $finding . ' ' . $scope);

        if ($this->isFood($text) || $this->containsAny($text, ['ccp', 'oprp', 'haccp', 'cleaning', 'traceability', 'temperature', 'pest'])) {
            if ($this->containsAny($text, ['trace', 'recall', 'withdrawal', 'dispatch'])) {
                return [
                    'correction' => 'Reconcile the affected lot/file against receiving, production/handling and dispatch records, then complete the missing traceability link and verify no other lot file has the same gap.',
                    'root_cause' => 'The traceability check did not require confirmation of all intermediate process references before the file was considered complete.',
                    'corrective_action' => 'Revise the traceability checklist, brief QA/dispatch personnel, and perform a scheduled mock trace sample to confirm full one-step-back/one-step-forward linkage.',
                    'preventive_action' => 'Include traceability completion rate and mock recall results in food safety team review and management review.',
                    'evidence_reference' => 'Corrected lot traceability file; revised traceability checklist; briefing record; mock traceability result.',
                    'verification' => 'Auditor to sample the corrected lot file and one additional lot to confirm full linkage from receiving to dispatch.',
                    'effectiveness' => 'Effectiveness confirmed when subsequent mock traceability is completed within the defined target time with complete linkage.',
                    'closure_notes' => 'CAPA closure must be linked to the original traceability finding: ' . $finding,
                ];
            }

            if ($this->containsAny($text, ['cleaning', 'sanitation', 'hygiene', 'pest', 'prp'])) {
                return [
                    'correction' => 'Complete verification of the affected PRP/cleaning record, inspect the area/control point, and document acceptability before continued use.',
                    'root_cause' => 'Shift-level verification responsibility and handover checks were not clearly defined for the affected PRP record.',
                    'corrective_action' => 'Update the PRP/cleaning verification checklist, brief supervisors, and review a defined sample of PRP records for completion each week.',
                    'preventive_action' => 'Trend PRP verification completion and recurring gaps during food safety team review.',
                    'evidence_reference' => 'Corrected PRP record; revised checklist; supervisor briefing; weekly PRP verification sample.',
                    'verification' => 'Auditor to verify completed PRP records and interview the responsible supervisor on verification responsibility.',
                    'effectiveness' => 'Effectiveness confirmed when follow-up PRP samples show complete verification before area/process release.',
                    'closure_notes' => 'CAPA closure must be linked to the original PRP/cleaning finding: ' . $finding,
                ];
            }

            return [
                'correction' => 'Review the affected CCP/OPRP or food safety monitoring record, document product/process disposition, and confirm no unsafe release occurred.',
                'root_cause' => 'The monitoring form and personnel briefing did not clearly require documented action for abnormal or near-limit results.',
                'corrective_action' => 'Revise monitoring records to require action/comment for abnormal trends, brief monitoring staff, and review the next month of records.',
                'preventive_action' => 'Include CCP/OPRP monitoring completion and abnormal-result follow-up in verification and management review inputs.',
                'evidence_reference' => 'Updated CCP/OPRP monitoring form; affected record review; staff briefing; follow-up monitoring sample.',
                'verification' => 'Auditor to verify revised monitoring record use and sample subsequent CCP/OPRP records for action recording.',
                'effectiveness' => 'Effectiveness confirmed when follow-up samples show complete action recording and supervisor verification.',
                'closure_notes' => 'CAPA closure must be linked to the original food safety control finding: ' . $finding,
            ];
        }

        if ($this->containsAny($text, ['competence', 'training', 'awareness'])) {
            return [
                'correction' => 'Review the affected personnel competence record, complete missing evaluation/training evidence, and confirm the person remains competent for assigned work.',
                'root_cause' => 'Competence evidence review was not consistently performed after role assignment or training completion.',
                'corrective_action' => 'Update the competence matrix review step, assign an owner, and sample competence records during the next internal audit.',
                'preventive_action' => 'Trend competence record completion and overdue evaluations during management review.',
                'evidence_reference' => 'Updated competence matrix; training/evaluation record; internal audit follow-up sample.',
                'verification' => 'Auditor to sample the updated competence file and interview the process owner on competence controls.',
                'effectiveness' => 'Effectiveness confirmed when sampled records show current competence evaluation before task assignment.',
                'closure_notes' => 'CAPA closure must be linked to the original competence finding: ' . $finding,
            ];
        }

        if ($this->containsAny($text, ['document', 'record', 'documented information'])) {
            return [
                'correction' => 'Correct or complete the affected documented information, verify current revision/approval, and remove or mark obsolete information where applicable.',
                'root_cause' => 'Documented information review and point-of-use control were not applied consistently for the sampled record/document.',
                'corrective_action' => 'Clarify document/record review responsibility, update the control checklist, and perform a sample check at point of use.',
                'preventive_action' => 'Add documented information control to periodic internal audit sampling.',
                'evidence_reference' => 'Corrected record/document; updated control checklist; point-of-use sample check.',
                'verification' => 'Auditor to verify corrected documented information and sample another controlled document/record.',
                'effectiveness' => 'Effectiveness confirmed when follow-up sampling shows correct approval, revision and retention control.',
                'closure_notes' => 'CAPA closure must be linked to the original documented information finding: ' . $finding,
            ];
        }

        return [
            'correction' => 'The affected record/process was immediately corrected, the missing information was completed where applicable, and the responsible process owner reviewed the affected sample to confirm containment.',
            'root_cause' => 'Root cause analysis identified that responsibility, verification frequency and escalation criteria were not sufficiently clear for the audited process. This allowed the weakness to recur without timely detection.',
            'corrective_action' => 'Revise the relevant procedure/checklist, brief the responsible personnel, add a periodic verification step, and include the requirement in the next internal audit sample for ' . $scope . '.',
            'preventive_action' => 'Extend the verification method to similar processes, trend the result during management review, and retain evidence of follow-up to prevent recurrence.',
            'evidence_reference' => 'Updated procedure/checklist; training attendance record; completed monitoring record; internal audit follow-up evidence; management review action log.',
            'verification' => 'Auditor to verify the revised control, interview the process owner, and sample completed records showing implementation against: ' . $requirement,
            'effectiveness' => 'Effectiveness to be confirmed by absence of recurrence in follow-up sampling and evidence that personnel understand the revised control.',
            'closure_notes' => 'CAPA closure requires acceptable correction, root cause, corrective action evidence and effectiveness verification linked to the original finding: ' . $finding,
        ];
    }

    private function stageLabel(string $eventType): string
    {
        return match ($eventType) {
            'initial_stage1' => 'Stage 1 audit',
            'initial_stage2' => 'Stage 2 audit',
            'surveillance1' => 'Surveillance 1 audit',
            'surveillance2' => 'Surveillance 2 audit',
            'recertification' => 'recertification audit',
            default => str_replace('_', ' ', $eventType ?: 'audit'),
        };
    }

    private function processTrail(array $planItems, string $standard, string $clauseTitle, string $scope): string
    {
        $items = [];
        foreach ($planItems as $item) {
            $process = trim((string) (($item['process_name'] ?? '') ?: ($item['department'] ?? '')));
            if ($process !== '' && ! in_array($process, $items, true)) {
                $items[] = $process;
            }
        }

        if ($items !== []) {
            return implode(', ', array_slice($items, 0, 4));
        }

        $lower = strtolower($standard . ' ' . $clauseTitle . ' ' . $scope);
        if (str_contains($lower, 'haccp') || str_contains($lower, 'food') || str_contains($lower, 'kitchen') || str_contains($lower, 'meal')) {
            return 'receiving, storage, food preparation, CCP/OPRP monitoring, cleaning and dispatch controls';
        }

        if (str_contains($lower, 'environment') || str_contains($lower, '14001')) {
            return 'environmental aspects, legal compliance, operational controls, emergency preparedness and monitoring records';
        }

        if (str_contains($lower, '45001') || str_contains($lower, 'health') || str_contains($lower, 'safety')) {
            return 'hazard identification, operational controls, consultation, incident handling and OH&S performance monitoring';
        }

        return 'management review, operational controls, support processes, performance evaluation and improvement records';
    }

    private function evidenceTrail(string $standard, string $clauseTitle, string $examples, string $scope): array
    {
        $evidence = array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', $examples) ?: [])));
        $lower = strtolower($standard . ' ' . $clauseTitle . ' ' . $scope);
        $specific = $this->clauseSpecificEvidence($standard, $clauseTitle, $scope);

        if ($specific !== []) {
            $evidence = array_merge($specific, $evidence);
        } elseif (str_contains($lower, 'haccp') || str_contains($lower, 'food') || str_contains($lower, 'kitchen') || str_contains($lower, 'meal')) {
            $evidence = array_merge([
                'approved HACCP plan, hazard analysis and CCP/OPRP monitoring records',
                'PRP records covering cleaning, pest control, personnel hygiene and temperature monitoring',
                'traceability/dispatch sample linked to receiving, production and delivery records',
            ], $evidence);
        } elseif (str_contains($lower, '9001') || str_contains($lower, 'quality')) {
            $evidence = array_merge([
                'process KPI records, customer feedback/complaint records and corrective action log',
                'documented procedure, competence record and internal audit evidence',
                'sampled operational record showing conformity to planned arrangements',
            ], $evidence);
        } else {
            $evidence = array_merge([
                'documented procedure and retained record relevant to the audited clause',
                'interview with process owner and review of implementation evidence',
                'site observation/sample trail confirming the control is applied',
            ], $evidence);
        }

        return array_slice(array_values(array_unique($evidence)), 0, 5);
    }

    private function clauseSpecificEvidence(string $standard, string $clauseTitle, string $scope): array
    {
        $text = strtolower($standard . ' ' . $clauseTitle . ' ' . $scope);

        if ($this->isFood($text)) {
            if ($this->containsAny($text, ['hazard', 'haccp', 'ccp', 'oprp', 'critical limit'])) {
                return [
                    'hazard analysis worksheet reviewed for product/process step, significant hazards and control measure selection',
                    'CCP/OPRP monitoring sample checked against defined limit/action criteria and corrective follow-up rule',
                    'food safety team discussion confirmed validation/verification method for selected controls',
                ];
            }

            if ($this->containsAny($text, ['prp', 'clean', 'sanitation', 'pest', 'hygiene', 'temperature', 'storage'])) {
                return [
                    'cleaning/sanitation record sample checked for area, chemical, frequency and verification sign-off',
                    'pest control and hygiene inspection records reviewed for trend and corrective follow-up',
                    'cold storage or holding temperature log sample checked for limit breach response',
                ];
            }

            if ($this->containsAny($text, ['trace', 'recall', 'withdrawal', 'release', 'dispatch'])) {
                return [
                    'traceability sample followed from receiving through preparation/processing to dispatch/customer reference',
                    'product release or dispatch record checked for identification, quantity, date and responsible approval',
                    'mock recall/withdrawal evidence reviewed for traceability result and corrective actions',
                ];
            }
        }

        if ($this->containsAny($text, ['context', 'interested part', 'scope'])) {
            return [
                'scope statement and process interaction reviewed against activities, sites and outsourced processes',
                'interested-party and internal/external issue review checked for current applicability',
                'management interview confirmed how scope boundaries and certification requirements are maintained',
            ];
        }

        if ($this->containsAny($text, ['leadership', 'policy', 'responsibilit', 'authority'])) {
            return [
                'policy communication and responsibility matrix reviewed for current approval and availability',
                'management interview confirmed accountability for objectives, customer/legal requirements and process performance',
                'meeting minutes sampled for management follow-up on system performance and resource needs',
            ];
        }

        if ($this->containsAny($text, ['risk', 'objective', 'planning', 'change'])) {
            return [
                'risk/opportunity register sampled for action owner, due date and review status',
                'objective/KPI record checked for target, actual performance, trend and action where required',
                'planned-change sample reviewed for controls, responsibility and implementation evidence',
            ];
        }

        if ($this->containsAny($text, ['competence', 'awareness', 'training'])) {
            return [
                'competence matrix sampled against assigned job role and audit scope activities',
                'training/awareness record checked for completion, evaluation and follow-up where gaps were identified',
                'interviewed personnel explained relevant procedure, monitoring record and escalation point',
            ];
        }

        if ($this->containsAny($text, ['document', 'information', 'record'])) {
            return [
                'documented information sample checked for approval, revision status and availability at point of use',
                'retained record sample checked for identification, legibility, retention and protection',
                'obsolete or superseded document control method reviewed with the process owner',
            ];
        }

        if ($this->containsAny($text, ['operation', 'production', 'service', 'control', 'supplier', 'external provider'])) {
            return [
                'operational record sampled against planned criteria, acceptance result and responsible sign-off',
                'supplier/external provider file reviewed for approval, monitoring and re-evaluation evidence',
                'process walkthrough confirmed controls were available and understood at the point of use',
            ];
        }

        if ($this->containsAny($text, ['internal audit', 'management review', 'performance', 'monitor', 'measurement'])) {
            return [
                'internal audit programme/report sampled for criteria, scope, findings and follow-up status',
                'management review minutes checked for required inputs, decisions, actions and assigned responsibility',
                'monitoring/KPI record reviewed for trend, analysis and action where performance was outside target',
            ];
        }

        if ($this->containsAny($text, ['nonconformity', 'corrective', 'improvement'])) {
            return [
                'corrective action sample checked for correction, cause analysis, action plan and verification result',
                'complaint or nonconforming output sample reviewed for containment, disposition and follow-up',
                'improvement action log checked for status, owner and evidence of completion',
            ];
        }

        if ($this->containsAny($text, ['environment', 'aspect', 'legal', 'compliance', 'emergency'])) {
            return [
                'environmental aspect/impact register sampled for significance criteria and operational controls',
                'legal compliance register and evaluation evidence checked for current obligations',
                'emergency preparedness record sampled for test result, learning points and follow-up action',
            ];
        }

        if ($this->containsAny($text, ['safety', 'hazard', 'incident', 'consultation', 'participation', 'oh&s', 'ohs'])) {
            return [
                'hazard identification and risk assessment sample checked for control hierarchy and residual risk',
                'incident/near-miss record reviewed for investigation, action and effectiveness follow-up',
                'consultation/participation evidence checked for worker input and communication of OH&S controls',
            ];
        }

        return [];
    }

    private function clauseFocus(string $clauseNumber, string $clauseTitle): string
    {
        $title = trim($clauseTitle);
        if ($title !== '') {
            return strtolower($title);
        }

        return trim($clauseNumber) !== '' ? 'clause ' . $clauseNumber : 'the audited requirement';
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

    private function documentEvidence(string $standard, string $clauseTitle, string $scope): array
    {
        $lower = strtolower($standard . ' ' . $clauseTitle . ' ' . $scope);

        if ($this->isFood($lower)) {
            return [
                'food safety manual and HACCP plan including product/process description, intended use and flow diagram',
                'hazard analysis worksheet with identified biological, chemical, physical and allergen hazards',
                'PRP/OPRP procedures for cleaning, sanitation, pest control, personal hygiene, waste, storage and temperature control',
                'CCP/OPRP monitoring procedure, critical limits/action criteria and correction/corrective action rules',
                'traceability, withdrawal/recall and product release procedure linked to catering dispatch controls',
            ];
        }

        if (str_contains($lower, '9001') || str_contains($lower, 'quality')) {
            return [
                'quality manual/process interaction map and documented scope of the management system',
                'procedure or process criteria defining responsibilities, inputs, outputs, risks and controls',
                'documented objectives, KPI monitoring method and customer complaint/feedback process',
                'internal audit procedure, management review procedure and corrective action procedure',
                'competence/training procedure and documented information control procedure',
            ];
        }

        if (str_contains($lower, '14001') || str_contains($lower, 'environment')) {
            return [
                'environmental aspects and impacts register with significance criteria',
                'legal compliance register and evaluation method',
                'operational control procedures for significant environmental aspects',
                'emergency preparedness and response arrangements',
                'monitoring, measurement, internal audit and management review procedures',
            ];
        }

        if (str_contains($lower, '45001') || str_contains($lower, 'safety')) {
            return [
                'hazard identification and risk assessment procedure/register',
                'OH&S legal and other requirements register',
                'operational control procedure, permit controls and emergency response arrangements',
                'incident/nonconformity investigation and corrective action procedure',
                'consultation, participation, competence and communication arrangements',
            ];
        }

        return [
            'documented management system procedure relevant to the clause',
            'scope statement, process map and responsibility matrix',
            'monitoring and measurement arrangements',
            'internal audit, management review and improvement procedures',
            'documented information and competence records control arrangements',
        ];
    }

    private function recordEvidence(string $standard, string $clauseTitle, string $scope): array
    {
        $lower = strtolower($standard . ' ' . $clauseTitle . ' ' . $scope);

        if ($this->isFood($lower)) {
            return [
                'receiving record sample checked for approved supplier, delivery condition, product temperature and acceptance decision',
                'cold storage/freezer temperature log sample checked for out-of-limit follow-up and supervisor review',
                'CCP cooking/chilling or OPRP monitoring record sample checked against defined limit/action criteria',
                'cleaning and sanitation record sample checked for area, chemical, concentration, frequency and verification sign-off',
                'pest control service report and trend record checked for corrective follow-up where activity was noted',
                'calibration/verification record for thermometer or monitoring device checked for traceability and due date',
                'traceability exercise sample followed from receiving through preparation/dispatch to customer/delivery record',
                'food handler hygiene/training record sample checked for competence and medical/fitness requirement where applicable',
                'internal audit and management review records checked for food safety performance, complaints and improvement actions',
            ];
        }

        if (str_contains($lower, '9001') || str_contains($lower, 'quality')) {
            return [
                'process KPI sample checked for target, actual result, trend and action where performance was below target',
                'customer complaint/feedback sample checked for evaluation, response, correction and corrective action where needed',
                'training/competence record sample checked against assigned role and process requirements',
                'documented information sample checked for approval, revision control and availability at point of use',
                'internal audit record checked for criteria, scope, findings, correction and follow-up',
                'management review record checked for inputs, outputs, decisions and improvement actions',
                'nonconformity/corrective action sample checked for root cause, action, verification and closure',
            ];
        }

        return [
            'procedure and retained record sample checked for consistency with the clause requirement',
            'process owner interview record and sampled transaction checked for implementation evidence',
            'monitoring or KPI record checked for review, trend and action where required',
            'internal audit and management review records checked for performance and improvement follow-up',
            'corrective action record checked for root cause, implementation evidence and effectiveness review',
        ];
    }

    private function interviewEvidence(string $standard, string $clauseTitle, array $auditTeam): array
    {
        $auditors = $this->auditorNames($auditTeam);
        $auditorText = $auditors !== '' ? 'Audit team: ' . $auditors . '.' : 'Audit team interviewed relevant personnel.';

        return [
            'process owner explained responsibility, sequence of activities, applicable controls and escalation method',
            'sampled personnel demonstrated awareness of applicable procedure, monitoring record and abnormal-condition response',
            'management representative confirmed review of performance, nonconformity status and improvement actions',
            $auditorText,
        ];
    }

    private function observationEvidence(string $standard, string $clauseTitle, string $scope, string $processes): array
    {
        $lower = strtolower($standard . ' ' . $clauseTitle . ' ' . $scope);

        if ($this->isFood($lower)) {
            return [
                'site walkthrough confirmed hygienic zoning, storage condition, segregation and general housekeeping controls',
                'food handling observation confirmed personnel hygiene, utensil/equipment condition and prevention of cross contamination',
                'monitoring point observation confirmed availability of thermometer/record and awareness of action criteria',
                'dispatch/holding observation confirmed product identification, temperature control and release/traceability arrangement',
                'audit trail covered ' . $processes . ' and was consistent with sampled retained records',
            ];
        }

        return [
            'process walkthrough confirmed that activities were performed in accordance with documented arrangements',
            'records observed at point of use were available, legible and controlled',
            'sampled implementation evidence was consistent with interviews and documented controls',
            'audit trail covered ' . $processes . ' and was consistent with sampled retained records',
        ];
    }

    private function isFood(string $text): bool
    {
        return str_contains($text, 'haccp')
            || str_contains($text, 'food')
            || str_contains($text, 'kitchen')
            || str_contains($text, 'meal')
            || str_contains($text, 'catering')
            || str_contains($text, 'iso 22000')
            || str_contains($text, '22000');
    }

    private function auditorNames(array $auditTeam): string
    {
        $names = [];
        foreach ($auditTeam as $member) {
            $name = trim((string) ($member['full_name'] ?? ''));
            if ($name !== '' && ! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return implode(', ', $names);
    }

    private function bulletList(array $items): string
    {
        return '- ' . implode("\n- ", $items);
    }
}
