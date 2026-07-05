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
        $documents = $this->documentEvidence($standard, $clauseTitle, $scope);
        $records = $this->recordEvidence($standard, $clauseTitle, $scope);
        $interviews = $this->interviewEvidence($standard, $clauseTitle, $auditTeam);
        $observations = $this->observationEvidence($standard, $clauseTitle, $scope, $processes);
        $auditors = $this->auditorNames($auditTeam);
        $auditNumber = trim((string) ($event['audit_number'] ?? ''));
        $auditDate = trim((string) (($event['planned_start_date'] ?? '') ?: ($event['actual_start_date'] ?? '')));

        return trim(
            "Conformity statement:\n"
            . "During the {$stage}" . ($auditNumber !== '' ? " ({$auditNumber})" : '') . ($auditDate !== '' ? " conducted from {$auditDate}" : '') . ", the audit team evaluated {$clauseNumber} {$clauseTitle} against the certified scope \"{$scope}\". "
            . "The audit trail was followed through {$processes}. The sampled evidence confirmed that the requirement is established, implemented, monitored and retained as documented information for the audited activities. "
            . "No objective evidence of nonconformity was identified for this clause at the time of audit.\n\n"
            . "Documents and controls reviewed:\n"
            . $this->bulletList($documents)
            . "\nRecords and objective evidence sampled:\n"
            . $this->bulletList($evidence)
            . "\nDetailed sample trail:\n"
            . $this->bulletList($records)
            . "\nInterview evidence:\n"
            . $this->bulletList($interviews)
            . "\nObservation evidence:\n"
            . $this->bulletList($observations)
            . "\nAuditor conclusion:\n"
            . "The reviewed documents, retained records, staff interviews and process observations were mutually consistent. Controls were implemented in line with the organization's documented arrangements and the applicable clause intent. "
            . "The conformity conclusion was recorded by " . ($auditors !== '' ? $auditors : 'the appointed audit team') . " based on sampling, objective evidence and professional judgement."
        );
    }

    public function ncrCorrectionSet(array $ncr, array $client = []): array
    {
        $requirement = trim((string) ($ncr['requirement'] ?? 'the applicable requirement'));
        $finding = trim((string) ($ncr['finding'] ?? 'the nonconformity'));
        $scope = trim((string) ($client['scope'] ?? 'the audited process'));

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

        if (str_contains($lower, 'haccp') || str_contains($lower, 'food') || str_contains($lower, 'kitchen') || str_contains($lower, 'meal')) {
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
