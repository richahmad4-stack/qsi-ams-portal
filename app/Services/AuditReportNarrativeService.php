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
        $evidence = $this->evidenceTrail($standard, $clauseNumber, $clauseTitle, (string) ($clause['evidence_examples'] ?? ''), $scope);
        $auditNumber = trim((string) ($event['audit_number'] ?? ''));
        $sampledEvidence = $this->withDocumentReferences(
            $client,
            $clauseNumber,
            $this->auditorEvidenceSet($standard, $clauseNumber, $clauseTitle, $scope, $processes, $evidence, $auditTeam)
        );
        $clauseFocus = $this->clauseFocus($clauseNumber, $clauseTitle);
        $systemName = $this->managementSystemName($standard, $scope);
        $scopeText = $scope !== '' ? $scope : 'the certified activities';
        $auditContext = "during {$stage}" . ($auditNumber !== '' ? " ({$auditNumber})" : '');

        return trim(
            "Conformity Statement (Auditor Style)\n\n"
            . "Conformity Note:\n"
            . "The organization has established, implemented and maintained controls relevant to {$standard} {$clauseNumber} - {$clauseTitle} within the scope \"{$scopeText}\". "
            . "The audit trail was sampled {$auditContext} and covered {$processes}. "
            . "Processes, records, interviews and site observations reviewed were consistent with the requirement for {$clauseFocus} and with the intended operation of the {$systemName}.\n\n"
            . "Objective Evidence:\n"
            . $this->bulletList($sampledEvidence)
            . "\n\nAuditor Conclusion:\n"
            . "Based on the records reviewed, personnel interviewed and activities observed during the audit trail for {$scopeText}, the organization demonstrated effective implementation of the applicable controls for {$standard} {$clauseNumber}. No nonconformities were identified against this sampled requirement."
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

    private function evidenceTrail(string $standard, string $clauseNumber, string $clauseTitle, string $examples, string $scope): array
    {
        $evidence = array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', $examples) ?: [])));
        $lower = strtolower($standard . ' ' . $clauseTitle . ' ' . $scope);
        $specific = $this->clauseSpecificEvidence($standard, $clauseNumber, $clauseTitle, $scope);

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

    private function auditorEvidenceSet(string $standard, string $clauseNumber, string $clauseTitle, string $scope, string $processes, array $clauseEvidence, array $auditTeam): array
    {
        $evidence = array_merge(
            array_slice($clauseEvidence, 0, 3),
            array_slice($this->recordEvidence($standard, $clauseTitle, $scope), 0, 2),
            array_slice($this->documentEvidence($standard, $clauseTitle, $scope), 0, 1),
            array_slice($this->observationEvidence($standard, $clauseTitle, $scope, $processes), 0, 1),
            array_slice($this->interviewEvidence($standard, $clauseTitle, $auditTeam), 0, 1)
        );

        return array_slice(array_values(array_unique($evidence)), 0, 8);
    }

    private function clauseSpecificEvidence(string $standard, string $clauseNumber, string $clauseTitle, string $scope): array
    {
        $standardText = strtolower($standard);
        $clauseText = strtolower(trim($clauseNumber . ' ' . $clauseTitle));
        $scopeText = strtolower($scope);
        $isFood = $this->isFood($standardText . ' ' . $scopeText);
        $isEnvironment = str_contains($standardText, '14001') || str_contains($standardText, 'environment');
        $isSafety = str_contains($standardText, '45001') || str_contains($standardText, 'safety') || str_contains($standardText, 'ohs');

        if ($this->startsWithClause($clauseNumber, 'FS.1') || ($isFood && $this->containsAny($clauseText, ['food safety hazards', 'hazard analysis']))) {
            return [
                'hazard analysis worksheet reviewed for product/process step, significant hazards and control measure selection',
                'food safety team review evidence checked for biological, chemical, physical and allergen hazard consideration',
                'validation/verification basis sampled for selected control measures linked to the audited food process',
            ];
        }

        if ($this->startsWithClause($clauseNumber, 'FS.2') || ($isFood && $this->containsAny($clauseText, ['prerequisite', 'prp']))) {
            return [
                'PRP programme sample reviewed for cleaning, pest control, personnel hygiene and storage/temperature control responsibilities',
                'cleaning or hygiene verification record checked for completion, review and follow-up of abnormal results',
                'site observation confirmed PRP controls were available at the relevant process area during the audit trail',
            ];
        }

        if ($this->startsWithClause($clauseNumber, 'FS.3') || ($isFood && $this->containsAny($clauseText, ['haccp plan', 'ccp', 'oprp', 'critical limit']))) {
            return [
                'approved HACCP plan sampled for CCP/OPRP identification, critical limits/action criteria and monitoring frequency',
                'CCP/OPRP monitoring record checked against defined limit/action criteria and correction/corrective action rules',
                'food safety team discussion confirmed verification method for selected HACCP controls',
            ];
        }

        if ($this->startsWithClause($clauseNumber, 'FS.4') || ($isFood && $this->containsAny($clauseText, ['traceability', 'withdrawal', 'recall']))) {
            return [
                'traceability sample followed from receiving through preparation/processing to dispatch/customer reference',
                'withdrawal/recall test evidence reviewed for traceability result, response time and follow-up actions',
                'product identification and release/dispatch record checked for lot/batch linkage and responsible approval',
            ];
        }

        if ($this->startsWithClause($clauseNumber, 'FS.5') || ($isFood && $this->containsAny($clauseText, ['emergency']))) {
            return [
                'food safety emergency scenario record reviewed for response responsibility and communication arrangements',
                'incident/withdrawal escalation evidence checked for contact list, decision authority and follow-up action',
                'management review or food safety team minutes sampled for emergency preparedness review',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '4.1')) {
            return [
                'internal/external issue register reviewed for relevance to the certified activities and current business conditions',
                'management review input sampled for changes affecting context, risks and certification scope',
                'management interview confirmed how context changes are reviewed and translated into system actions',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '4.2')) {
            return [
                'interested-party register sampled for customer, regulatory, supplier and employee requirements',
                'legal/customer requirement review checked for current applicability and assigned responsibility',
                'sampled communication or contract record confirmed relevant interested-party needs were considered',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '4.3')) {
            return [
                'management system scope statement reviewed against activities, sites, products/services and exclusions',
                'certificate/application scope compared with actual audited processes and client operational boundaries',
                'outsourced or multi-site activity sample checked for inclusion/exclusion in scope justification',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '4.4')) {
            return [
                'process interaction map sampled for inputs, outputs, responsibilities and sequence of key activities',
                'process criteria/KPI sample checked for monitoring method, owner and evidence of review',
                'audit trail followed one core process to confirm linkage between procedure, record and performance review',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '5.1')) {
            return [
                'top management interview confirmed accountability for policy, objectives, resources and customer/legal obligations',
                'management review/action record sampled for leadership follow-up on system performance',
                'communication evidence reviewed for management involvement in certification requirements and process performance',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '5.2')) {
            return [
                'policy document checked for approval, suitability to scope and availability to relevant personnel',
                'sampled personnel interview confirmed awareness of policy intent and relevant obligations',
                'policy communication evidence reviewed through display, induction or controlled document distribution',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '5.3')) {
            return [
                'responsibility matrix or job description sampled for assigned process authority and reporting lines',
                'interview with process owner confirmed understanding of responsibility for records, controls and escalation',
                'organization chart or appointment record checked for current approval and role assignment',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '6.1')) {
            if ($isEnvironment) {
                return [
                    'environmental aspects and impacts register sampled for significance criteria and operational control linkage',
                    'compliance obligation review checked for applicable legal/customer environmental requirements',
                    'risk/opportunity action sample reviewed for owner, due date and evidence of implementation',
                ];
            }

            if ($isSafety) {
                return [
                    'hazard identification/risk assessment sample checked for activity, hazard, controls and residual risk',
                    'legal/other OH&S requirement register reviewed for current applicability and control linkage',
                    'worker consultation evidence sampled for participation in risk control review',
                ];
            }

            return [
                'risk/opportunity register sampled for source, evaluation, action owner, due date and review status',
                'planning action evidence checked for implementation and linkage to process objectives/controls',
                'management review or process review record sampled for risk/action status updates',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '6.2')) {
            return [
                'objective/KPI record sampled for target, measurement method, actual result and trend review',
                'action plan for objective achievement checked for owner, resources, due date and progress update',
                'management/process review minutes sampled for decisions where objectives were not achieved',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '6.3')) {
            return [
                'planned-change sample reviewed for reason, affected process, risk review and implementation control',
                'approval/communication evidence checked for the selected change before implementation',
                'post-change review evidence sampled for effectiveness and any follow-up action',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '7.1')) {
            return [
                'resource plan or equipment/facility record sampled for adequacy against process needs',
                'maintenance/calibration/infrastructure record checked where relevant to the audited process',
                'management interview confirmed resource needs are reviewed when performance or workload changes',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '7.2')) {
            return [
                'competence matrix sampled against assigned job role and audit scope activities',
                'training/awareness record checked for completion, evaluation and follow-up where gaps were identified',
                'interviewed personnel explained relevant procedure, monitoring record and escalation point',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '7.3')) {
            return [
                'awareness evidence sampled through interview on policy, objectives, relevant procedures and consequences of nonconformity',
                'induction/toolbox/briefing record checked for coverage of applicable process controls',
                'sampled personnel demonstrated where to access current instructions and how to escalate abnormal conditions',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '7.4')) {
            return [
                'internal/external communication matrix sampled for what, when, with whom and responsible person',
                'customer/regulatory/supplier communication sample checked for response, follow-up and retained evidence',
                'interview confirmed communication channels for process changes, complaints or abnormal events',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '7.5')) {
            return [
                'documented information sample checked for approval, revision status and availability at point of use',
                'retained record sample checked for identification, legibility, retention and protection',
                'obsolete or superseded document control method reviewed with the process owner',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '8.1')) {
            return [
                'operational planning record sampled for process criteria, controls, resources and acceptance requirements',
                'process walkthrough confirmed planned controls were available and applied at the point of use',
                'retained operational record checked for completion against defined criteria',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '8.2')) {
            return [
                'customer/contract requirement review sample checked for scope, capability, statutory/regulatory needs and acceptance',
                'change or enquiry review evidence sampled for communication of revised requirements',
                'customer communication record checked for response, clarification and retained approval where applicable',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '8.3')) {
            return [
                'design/development applicability or exclusion justification reviewed against certified scope',
                'design input/output/review sample checked where design activity was applicable',
                'change validation or approval evidence sampled for design-related process changes where relevant',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '8.4')) {
            return [
                'approved supplier/external provider record sampled for approval criteria and current status',
                'purchase/service control sample checked for communicated requirements and acceptance evidence',
                'supplier monitoring or re-evaluation record reviewed for performance and follow-up action',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '8.5')) {
            return [
                $isFood
                    ? 'production/food handling record sampled for process control, hygiene/temperature condition and responsible sign-off'
                    : 'production/service provision record sampled against planned criteria, acceptance result and responsible sign-off',
                'process walkthrough confirmed instructions, equipment/resources and monitoring controls were available at point of use',
                'identification/status or traceability control sample checked through the audited operational flow',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '8.6')) {
            return [
                'release/acceptance record sampled for verification against defined criteria before delivery/use',
                'responsible approval or release authority checked for the selected output/batch/service record',
                'non-release or hold control reviewed where acceptance evidence was incomplete or pending',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '8.7')) {
            return [
                'nonconforming output/product/service sample reviewed for identification, segregation/control and disposition',
                'correction/rework/concession record checked for authority and customer/regulatory communication where applicable',
                'follow-up evidence sampled to confirm affected output was controlled before release or delivery',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '9.1')) {
            return [
                'monitoring/measurement record sampled for defined method, frequency, result and acceptance criteria',
                'KPI/performance trend reviewed for analysis, action where below target and management/process review follow-up',
                'calibration/verification record checked where measuring equipment was used for acceptance decisions',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '9.2')) {
            return [
                'internal audit programme/report sampled for criteria, scope, findings and follow-up status',
                'auditor competence/independence evidence checked for the selected internal audit sample',
                'internal audit correction/action evidence reviewed for closure and verification of sampled findings',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '9.3')) {
            return [
                'management review minutes checked for required inputs, outputs, decisions and assigned responsibilities',
                'status of actions from previous review sampled for completion and effectiveness evidence',
                'performance, audit, complaint/nonconformity and objective trends reviewed for management decisions',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '10.1')) {
            return [
                'improvement opportunity/action log sampled for source, owner, target date and current status',
                'management/process review evidence checked for decisions on improvement priorities',
                'completed improvement sample reviewed for implementation evidence and result achieved',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '10.2')) {
            return [
                'corrective action sample checked for correction, cause analysis, action plan and verification result',
                'complaint or nonconforming output sample reviewed for containment, disposition and follow-up',
                'improvement action log checked for status, owner and evidence of completion',
            ];
        }

        if ($this->startsWithClause($clauseNumber, '10.3')) {
            return [
                'continual improvement trend reviewed through objectives, audit results, complaints/nonconformities and management review actions',
                'sampled improvement project/action checked for implementation evidence and measured benefit where available',
                'management review output sampled for decisions supporting ongoing system improvement',
            ];
        }

        if ($this->containsAny($clauseText, ['environment', 'aspect', 'legal', 'compliance', 'emergency'])) {
            return $isEnvironment
                ? [
                    'environmental aspect/impact register sampled for significance criteria and operational controls',
                    'legal compliance register and evaluation evidence checked for current obligations',
                    'emergency preparedness record sampled for test result, learning points and follow-up action',
                ]
                : [];
        }

        if ($this->containsAny($clauseText, ['safety', 'hazard', 'incident', 'consultation', 'participation', 'oh&s', 'ohs'])) {
            return $isSafety
                ? [
                    'hazard identification and risk assessment sample checked for control hierarchy and residual risk',
                    'incident/near-miss record reviewed for investigation, action and effectiveness follow-up',
                    'consultation/participation evidence checked for worker input and communication of OH&S controls',
                ]
                : [];
        }

        return [];
    }

    private function startsWithClause(string $clauseNumber, string $prefix): bool
    {
        return str_starts_with(strtoupper(trim($clauseNumber)), strtoupper($prefix));
    }

    private function withDocumentReferences(array $client, string $clauseNumber, array $evidence): array
    {
        $companyCode = $this->companyReferenceCode($client);
        $clauseCode = $this->clauseReferenceCode($clauseNumber);

        return array_map(
            static fn (string $item, int $index): string => rtrim($item, '.') . ' (Ref: ' . $companyCode . '-' . $clauseCode . '-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT) . ')',
            array_values($evidence),
            array_keys(array_values($evidence))
        );
    }

    private function companyReferenceCode(array $client): string
    {
        $name = strtoupper((string) ($client['company'] ?? $client['company_name'] ?? $client['client_name'] ?? 'QSI'));
        $words = preg_split('/[^A-Z0-9]+/', $name) ?: [];
        $skip = [
            'AL', 'EL', 'THE', 'DEMO', 'LLC', 'LTD', 'CO', 'COMPANY', 'CORP', 'GROUP',
            'SERVICES', 'SERVICE', 'MANAGEMENT', 'INDUSTRIES', 'INDUSTRY', 'FACTORY',
            'FACILITIES', 'PROCESSING', 'CATERING',
        ];

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
        $code = strtoupper(trim($clauseNumber));
        $code = preg_replace('/[^A-Z0-9.]+/', '', $code) ?: 'GEN';

        return $code;
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

    private function managementSystemName(string $standard, string $scope): string
    {
        $lower = strtolower($standard . ' ' . $scope);

        if ($this->isFood($lower)) {
            return 'Food Safety Management System';
        }

        if (str_contains($lower, '9001') || str_contains($lower, 'quality')) {
            return 'Quality Management System';
        }

        if (str_contains($lower, '14001') || str_contains($lower, 'environment')) {
            return 'Environmental Management System';
        }

        if (str_contains($lower, '45001') || str_contains($lower, 'safety') || str_contains($lower, 'ohs')) {
            return 'OH&S Management System';
        }

        return 'management system';
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
