<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SeedControlledIntegratedAuditRequirements extends Migration
{
    private const SOURCE = 'QSI controlled audit-question catalogue; ISO editions fixed by CertificationBaseline and Codex CXC 1-1969 (2022 revision).';

    public function up(): void
    {
        if (! $this->db->tableExists('integrated_audit_requirements')) {
            return;
        }

        $standardRows = $this->db->table('standards')
            ->select('id, code')
            ->whereIn('code', ['ISO 9001:2015', 'ISO 14001:2015', 'ISO 45001:2018', 'ISO 22000:2018', 'HACCP'])
            ->get()
            ->getResultArray();
        $standardIds = array_column($standardRows, 'id', 'code');

        foreach ($this->db->table('tenants')->select('id')->get()->getResultArray() as $tenant) {
            $tenantId = (int) $tenant['id'];
            $approverId = $this->superAdminId($tenantId);

            foreach ($this->catalogue() as $item) {
                $existing = $this->db->table('integrated_audit_requirements')
                    ->select('id')
                    ->where('tenant_id', $tenantId)
                    ->where('requirement_code', $item['code'])
                    ->where('version_no', 1)
                    ->get()
                    ->getRowArray();

                $row = [
                    'tenant_id' => $tenantId,
                    'requirement_code' => $item['code'],
                    'title' => $item['title'],
                    'audit_question' => $item['question'],
                    'evidence_guidance' => $item['evidence'],
                    'requirement_family' => $item['family'],
                    'stage_applicability' => $item['stages'],
                    'source_type' => 'qsi_authored_controlled',
                    'version_no' => 1,
                    'active' => 1,
                    'created_by' => $approverId,
                    'approved_by' => $approverId,
                    'approved_at' => '2026-08-10 00:00:00',
                ];

                if ($existing === null) {
                    $this->db->table('integrated_audit_requirements')->insert($row);
                    $requirementId = (int) $this->db->insertID();
                } else {
                    $requirementId = (int) $existing['id'];
                    unset($row['tenant_id'], $row['requirement_code'], $row['version_no']);
                    $this->db->table('integrated_audit_requirements')->where('id', $requirementId)->update($row);
                    $this->db->table('integrated_requirement_clauses')->where('audit_requirement_id', $requirementId)->delete();
                }

                $mappings = [];
                foreach ($item['mappings'] as $standardCode => $mapping) {
                    if (! isset($standardIds[$standardCode])) {
                        continue;
                    }
                    $mappings[] = [
                        'audit_requirement_id' => $requirementId,
                        'standard_id' => (int) $standardIds[$standardCode],
                        'clause_library_id' => null,
                        'clause_reference' => $mapping[0],
                        'clause_title_snapshot' => $mapping[1],
                        'mapping_role' => 'primary',
                    ];
                }
                if ($mappings !== []) {
                    $this->db->table('integrated_requirement_clauses')->insertBatch($mappings);
                }
            }
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('integrated_audit_requirements')) {
            return;
        }

        $codes = array_column($this->catalogue(), 'code');
        if ($codes !== []) {
            $this->db->table('integrated_audit_requirements')->whereIn('requirement_code', $codes)->delete();
        }
    }

    private function superAdminId(int $tenantId): ?int
    {
        $row = $this->db->table('users')
            ->select('users.id')
            ->join('user_role_assignments assignments', 'assignments.user_id = users.id', 'left')
            ->join('roles assigned_roles', 'assigned_roles.id = assignments.role_id', 'left')
            ->join('roles primary_role', 'primary_role.id = users.primary_role_id', 'left')
            ->where('users.tenant_id', $tenantId)
            ->groupStart()
                ->where('assigned_roles.code', 'super_admin')
                ->orWhere('primary_role.code', 'super_admin')
            ->groupEnd()
            ->orderBy('users.id', 'ASC')
            ->get()
            ->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    private function catalogue(): array
    {
        $allIso = ['ISO 9001:2015', 'ISO 14001:2015', 'ISO 45001:2018', 'ISO 22000:2018'];
        $common = static fn (string $reference, string $title): array => array_fill_keys($allIso, [$reference, $title]);
        $operationStages = 'initial_stage2,surveillance1,surveillance2,recertification';

        return [
            $this->r('QSI-COM-04.01', 'Organizational context', 'How has the organization identified the internal and external conditions that can affect the intended results of the management system?', 'Context review, strategic or business plan, risk register, market/regulatory analysis and evidence that changes are reconsidered.', 'integrated_management', 'all', $common('4.1', 'Context of the organization')),
            $this->r('QSI-COM-04.02', 'Interested parties', 'How are relevant interested parties and their applicable needs or obligations identified, maintained and reviewed?', 'Interested-party register, legal/customer/worker requirements, review records and links to system controls.', 'integrated_management', 'all', $common('4.2', 'Needs and expectations of interested parties')),
            $this->r('QSI-COM-04.03', 'Management system scope', 'Does the documented scope accurately cover the certified sites, activities, products, services, boundaries and justified applicability decisions?', 'Approved scope statement, application/certificate comparison, site/process map, exclusions or applicability rationale and outsourced activities.', 'integrated_management', 'all', $common('4.3', 'Scope of the management system')),
            $this->r('QSI-COM-04.04', 'System processes and interaction', 'Are the processes needed for the management system defined, controlled, resourced, measured and linked through clear inputs, outputs and responsibilities?', 'Process map, procedures, KPIs, ownership matrix, interaction records and sampled process controls.', 'integrated_management', 'all', $common('4.4', 'Management system and its processes')),
            $this->r('QSI-COM-05.01', 'Leadership and accountability', 'How does top management demonstrate active accountability for the management system and its intended results?', 'Leadership interviews, business integration, resource decisions, performance review, communication and follow-up actions.', 'integrated_management', 'all', $common('5.1', 'Leadership and commitment')),
            $this->r('QSI-COM-05.02', 'Policy', 'Is the applicable policy established, communicated, available and consistent with the organization, commitments and strategic direction?', 'Approved policy, communication and awareness records, availability, review history and links to objectives.', 'integrated_management', 'all', $common('5.2', 'Policy')),
            $this->r('QSI-COM-05.03', 'Roles and authorities', 'Are relevant responsibilities, authorities and reporting lines assigned, understood and maintained?', 'Organization chart, job descriptions, appointment letters, responsibility matrix, interviews and delegated authorities.', 'integrated_management', 'all', $common('5.3', 'Roles, responsibilities and authorities')),
            $this->r('QSI-COM-06.01', 'Risks and opportunities', 'Are management-system risks and opportunities identified, prioritized, acted on and reviewed for effectiveness?', 'Risk/opportunity register, criteria, action plans, operational links, review records and effectiveness results.', 'integrated_management', 'all', $common('6.1', 'Actions addressing risks and opportunities')),
            $this->r('QSI-COM-06.02', 'Objectives and planning', 'Are measurable objectives established at relevant levels with responsibilities, resources, timing, monitoring and evaluation methods?', 'Approved objectives, targets, action plans, owners, resources, progress monitoring, results and revisions.', 'integrated_management', 'all', $common('6.2', 'Objectives and planning to achieve them')),
            $this->r('QSI-COM-07.01', 'Resources', 'Are people, infrastructure, work environment, monitoring resources and organizational knowledge adequate for the certified scope?', 'Resource plans, maintenance, staffing, calibrated equipment, workplace conditions, budgets and management decisions.', 'integrated_management', 'all', $common('7.1', 'Resources')),
            $this->r('QSI-COM-07.02', 'Competence', 'Has required competence been determined and demonstrated for personnel whose work can affect management-system performance?', 'Competence matrix, qualifications, experience, training, evaluation, authorization and effectiveness checks.', 'integrated_management', 'all', $common('7.2', 'Competence')),
            $this->r('QSI-COM-07.03', 'Awareness', 'Do sampled personnel understand the policy, objectives, relevant controls, their contribution and the consequences of not following requirements?', 'Personnel interviews, induction/toolbox records, awareness material, observations and competence follow-up.', 'integrated_management', 'all', $common('7.3', 'Awareness')),
            $this->r('QSI-COM-07.04', 'Communication', 'Are internal and external communications planned and controlled for what, when, with whom, how and by whom?', 'Communication matrix, regulatory/customer/worker communications, escalation records, notices and retained evidence.', 'integrated_management', 'all', $common('7.4', 'Communication')),
            $this->r('QSI-COM-07.05', 'Documented information', 'Are documents and records identified, approved, protected, available, retained and controlled through their lifecycle?', 'Document master list, approvals, revisions, access controls, retention rules, archived records and obsolete-copy controls.', 'integrated_management', 'all', $common('7.5', 'Documented information')),
            $this->r('QSI-COM-08.01', 'Operational planning and control', 'Are operational criteria translated into effective controls for relevant processes, planned changes and outsourced activities?', 'Operational procedures, work instructions, control plans, change records, outsourced-process controls and sampled implementation.', 'integrated_management', $operationStages, $common('8.1', 'Operational planning and control')),
            $this->r('QSI-COM-09.01', 'Monitoring and evaluation', 'Are meaningful indicators monitored and analyzed using valid methods, and are results used to evaluate system performance?', 'KPI results, monitoring plans, trend analysis, inspection/test data, compliance results and follow-up decisions.', 'integrated_management', 'all', $common('9.1', 'Monitoring, measurement, analysis and evaluation')),
            $this->r('QSI-COM-09.02', 'Internal audit', 'Does the internal audit programme cover relevant processes, risks, sites and requirements using competent and impartial auditors?', 'Audit programme, plans, checklists, reports, auditor competence/independence, findings and corrective-action follow-up.', 'integrated_management', 'all', $common('9.2', 'Internal audit')),
            $this->r('QSI-COM-09.03', 'Management review', 'Does top management review required inputs, evaluate system suitability and performance, and assign controlled outputs and actions?', 'Management review agenda, inputs, minutes, decisions, resources, action owners, due dates and closure evidence.', 'integrated_management', 'all', $common('9.3', 'Management review')),
            $this->r('QSI-COM-10.02', 'Nonconformity and corrective action', 'Are nonconformities contained, corrected, analyzed for cause, addressed to prevent recurrence and verified for effectiveness?', 'NCR/CAPA log, correction, root-cause analysis, action plan, evidence, effectiveness review and system updates.', 'integrated_management', 'all', $common('10.2', 'Nonconformity and corrective action')),
            $this->r('QSI-COM-10.03', 'Continual improvement', 'How does the organization identify and implement improvements based on performance, audits, review, feedback and changing conditions?', 'Improvement register, trend analysis, projects, lessons learned, review decisions and demonstrated results.', 'integrated_management', 'all', [
                'ISO 9001:2015' => ['10.3', 'Continual improvement'],
                'ISO 14001:2015' => ['10.3', 'Continual improvement'],
                'ISO 45001:2018' => ['10.3', 'Continual improvement'],
                'ISO 22000:2018' => ['10.2', 'Continual improvement'],
            ]),

            $this->r('QSI-QMS-08.02', 'Customer and service requirements', 'Are customer, legal and service/product requirements determined, reviewed, communicated and controlled before commitment and when changed?', 'Enquiries, quotations, contracts, order review, legal requirements, changes and customer communications.', 'quality', $operationStages, ['ISO 9001:2015' => ['8.2', 'Requirements for products and services']]),
            $this->r('QSI-QMS-08.03', 'Design and development', 'Where applicable, is design or development planned and controlled through inputs, reviews, verification, validation, outputs and changes?', 'Applicability rationale, design plans, inputs, reviews, verification/validation, outputs and change controls.', 'quality', $operationStages, ['ISO 9001:2015' => ['8.3', 'Design and development']]),
            $this->r('QSI-QMS-08.04', 'External providers', 'Are external providers and outsourced processes selected, monitored and controlled according to their effect on conformity?', 'Approved supplier list, evaluations, purchase criteria, specifications, incoming checks, performance monitoring and re-evaluation.', 'quality', $operationStages, ['ISO 9001:2015' => ['8.4', 'Control of externally provided processes, products and services']]),
            $this->r('QSI-QMS-08.05', 'Production and service provision', 'Are production or service activities carried out under controlled conditions, including identification, preservation, property and change control?', 'Work instructions, process parameters, job/service records, identification, customer property, preservation and approved changes.', 'quality', $operationStages, ['ISO 9001:2015' => ['8.5', 'Production and service provision']]),
            $this->r('QSI-QMS-08.06', 'Release authorization', 'Is release of products or services supported by completed acceptance evidence and identifiable authorization?', 'Inspection/test results, acceptance criteria, release records, authorized sign-off and traceability to the output.', 'quality', $operationStages, ['ISO 9001:2015' => ['8.6', 'Release of products and services']]),
            $this->r('QSI-QMS-08.07', 'Nonconforming outputs', 'Are nonconforming outputs identified, controlled, dispositioned and reverified where correction is made?', 'Nonconforming-output log, identification, segregation, concession, correction, re-verification and customer notification.', 'quality', $operationStages, ['ISO 9001:2015' => ['8.7', 'Control of nonconforming outputs']]),
            $this->r('QSI-QMS-09.12', 'Customer satisfaction', 'How is customer perception monitored, analyzed and used to improve products, services and the management system?', 'Feedback, complaints, surveys, retention/repeat business, trend analysis and resulting actions.', 'quality', $operationStages, ['ISO 9001:2015' => ['9.1.2', 'Customer satisfaction']]),

            $this->r('QSI-EMS-06.12', 'Environmental aspects', 'Are environmental aspects and significant impacts determined using defined lifecycle-aware criteria and kept current when activities change?', 'Aspect/impact register, significance method, lifecycle considerations, normal/abnormal/emergency conditions and change reviews.', 'environment', $operationStages, ['ISO 14001:2015' => ['6.1.2', 'Environmental aspects']]),
            $this->r('QSI-EMS-06.13', 'Compliance obligations', 'Are applicable environmental legal and other obligations identified, accessible, translated into controls and kept current?', 'Legal register, permits, regulator requirements, updates, operational links and assigned responsibilities.', 'environment', $operationStages, ['ISO 14001:2015' => ['6.1.3', 'Compliance obligations']]),
            $this->r('QSI-EMS-08.01', 'Environmental operational controls', 'Are significant environmental aspects, lifecycle considerations and outsourced activities controlled using defined operating criteria?', 'Operational controls, supplier requirements, waste/emission controls, maintenance, lifecycle communication and observations.', 'environment', $operationStages, ['ISO 14001:2015' => ['8.1', 'Operational planning and control']]),
            $this->r('QSI-EMS-08.02', 'Environmental emergency response', 'Are credible environmental emergency situations planned for, tested, reviewed and improved?', 'Emergency scenarios, response plans, drills, equipment checks, lessons learned and revised arrangements.', 'environment', $operationStages, ['ISO 14001:2015' => ['8.2', 'Emergency preparedness and response']]),
            $this->r('QSI-EMS-09.12', 'Evaluation of environmental compliance', 'Is compliance evaluated at planned intervals, with status known and any required actions completed?', 'Compliance evaluation plan, legal/permit checks, results, noncompliance actions and retained status records.', 'environment', $operationStages, ['ISO 14001:2015' => ['9.1.2', 'Evaluation of compliance']]),

            $this->r('QSI-OHS-05.04', 'Worker consultation and participation', 'Are workers and their representatives effectively consulted and enabled to participate in relevant OH&S decisions and controls?', 'Committee records, worker interviews, hazard reports, consultation on changes, participation arrangements and barrier removal.', 'ohs', 'all', ['ISO 45001:2018' => ['5.4', 'Consultation and participation of workers']]),
            $this->r('QSI-OHS-06.12', 'Hazard identification and OH&S risk', 'Are hazards proactively identified and OH&S risks/opportunities assessed for routine, non-routine, human, organizational and change factors?', 'Hazard/risk register, methodology, worker input, incidents, changes, vulnerable groups and effectiveness review.', 'ohs', $operationStages, ['ISO 45001:2018' => ['6.1.2', 'Hazard identification and assessment of risks and opportunities']]),
            $this->r('QSI-OHS-06.13', 'OH&S legal requirements', 'Are applicable OH&S legal and other requirements identified, updated and reflected in risk and operational controls?', 'Legal register, licenses, statutory inspections, updates, compliance responsibilities and operational links.', 'ohs', $operationStages, ['ISO 45001:2018' => ['6.1.3', 'Legal requirements and other requirements']]),
            $this->r('QSI-OHS-08.12', 'Hierarchy of controls', 'Are OH&S risks reduced through a documented preference for elimination, substitution and engineering controls before administrative controls or PPE?', 'Risk treatment decisions, design/procurement controls, engineering changes, procedures, PPE and effectiveness checks.', 'ohs', $operationStages, ['ISO 45001:2018' => ['8.1.2', 'Eliminating hazards and reducing OH&S risks']]),
            $this->r('QSI-OHS-08.13', 'Management of change', 'Are temporary and permanent changes assessed and controlled before implementation, including unintended consequences?', 'Change requests, pre-change risk review, approvals, communication, training and post-change evaluation.', 'ohs', $operationStages, ['ISO 45001:2018' => ['8.1.3', 'Management of change']]),
            $this->r('QSI-OHS-08.14', 'Procurement, contractors and outsourcing', 'Are OH&S criteria applied to procurement, contractors and outsourced functions, with coordination and performance controls?', 'Procurement specifications, contractor prequalification, induction, coordination, permits, monitoring and outsourced controls.', 'ohs', $operationStages, ['ISO 45001:2018' => ['8.1.4', 'Procurement']]),
            $this->r('QSI-OHS-08.20', 'OH&S emergency response', 'Are likely OH&S emergencies planned, resourced, tested with worker participation and reviewed after drills or events?', 'Emergency plans, first aid/fire arrangements, drills, equipment, worker participation, evaluations and improvements.', 'ohs', $operationStages, ['ISO 45001:2018' => ['8.2', 'Emergency preparedness and response']]),
            $this->r('QSI-OHS-10.20', 'Incident investigation and corrective action', 'Are incidents and near misses reported, investigated with worker participation, corrected and used to prevent recurrence?', 'Incident reports, immediate actions, investigation/root cause, worker participation, corrective actions and effectiveness review.', 'ohs', $operationStages, ['ISO 45001:2018' => ['10.2', 'Incident, nonconformity and corrective action']]),

            $this->r('QSI-FSMS-08.02', 'Prerequisite programmes and GHPs', 'Are prerequisite and good hygiene programmes selected for the food-chain context, implemented across relevant areas and routinely verified?', 'PRP/GHP programme, zoning, cleaning, pest control, personnel hygiene, utilities, maintenance, storage and verification records.', 'food_safety', 'all', [
                'ISO 22000:2018' => ['8.2', 'Prerequisite programmes'],
                'HACCP' => ['GHP', 'Good hygiene practices'],
            ]),
            $this->r('QSI-FSMS-08.03', 'Traceability system', 'Can selected materials, ingredients, work-in-process, rework and dispatched products be traced through the defined system and tested within target time?', 'Traceability procedure, coding, receiving/processing/release/dispatch records, mass balance and mock trace results.', 'food_safety', $operationStages, ['ISO 22000:2018' => ['8.3', 'Traceability system']]),
            $this->r('QSI-FSMS-08.04', 'Food-safety emergency response', 'Are potential food-safety emergencies identified, communicated, tested and reviewed with clear responsibilities?', 'Emergency scenarios, contact lists, response/communication records, exercises, incident learning and updates.', 'food_safety', $operationStages, ['ISO 22000:2018' => ['8.4', 'Emergency preparedness and response']]),
            $this->r('QSI-FSMS-08.52', 'Hazard analysis', 'Does the food safety team use current product, process and intended-use information to identify and assess reasonably expected hazards?', 'Food safety team records, product descriptions, intended use, flow diagrams, hazard analysis, severity/likelihood criteria and rationale.', 'food_safety', 'all', [
                'ISO 22000:2018' => ['8.5.2', 'Hazard analysis'],
                'HACCP' => ['HACCP Principle 1', 'Conduct hazard analysis and identify controls'],
            ]),
            $this->r('QSI-FSMS-08.53', 'Validation of control measures', 'Are selected control measures and combinations validated before implementation and after relevant changes?', 'Scientific/technical basis, studies, supplier data, regulatory criteria, calculations, trials and revalidation records.', 'food_safety', $operationStages, ['ISO 22000:2018' => ['8.5.3', 'Validation of control measures']]),
            $this->r('QSI-FSMS-08.54', 'Hazard control plan', 'Are OPRP and CCP controls clearly defined with hazards, limits/action criteria, monitoring, responsibilities, corrections and records?', 'HACCP/OPRP plan, limits, monitoring methods/frequency, responsibility, corrections, corrective actions and records.', 'food_safety', $operationStages, ['ISO 22000:2018' => ['8.5.4', 'Hazard control plan']]),
            $this->r('QSI-FSMS-08.60', 'Updating food-safety control information', 'Are PRP and hazard-control documents reviewed and updated when product, process, equipment, legal or hazard information changes?', 'Change records, revised product/process data, updated flow diagrams, hazard analysis, plans and approvals.', 'food_safety', $operationStages, ['ISO 22000:2018' => ['8.6', 'Updating information specifying PRPs and the hazard control plan']]),
            $this->r('QSI-FSMS-08.70', 'Monitoring and measuring control', 'Are monitoring and measuring methods/equipment suitable, calibrated or verified, protected and supported by valid records?', 'Equipment register, calibration/verification, methods, reference standards, out-of-tolerance review and monitoring records.', 'food_safety', $operationStages, ['ISO 22000:2018' => ['8.7', 'Control of monitoring and measuring']]),
            $this->r('QSI-FSMS-08.80', 'Verification of PRPs and hazard controls', 'Are verification activities planned, independent where needed, completed and analyzed by the food safety team?', 'Verification plan, PRP checks, record review, sampling/testing, internal verification, analysis and follow-up.', 'food_safety', $operationStages, ['ISO 22000:2018' => ['8.8', 'Verification related to PRPs and the hazard control plan']]),
            $this->r('QSI-FSMS-08.90', 'Control of food-safety nonconformity and withdrawal', 'Are affected products evaluated and controlled, corrections/corrective actions completed, and withdrawal/recall capability maintained and tested?', 'Deviation records, product disposition/release, correction/CAPA, withdrawal/recall procedure, notifications and mock recall.', 'food_safety', $operationStages, ['ISO 22000:2018' => ['8.9', 'Control of product and process nonconformities']]),

            $this->r('QSI-HACCP-STEP01', 'HACCP team and scope', 'Is a multidisciplinary HACCP team appointed with suitable knowledge, authority and a defined study scope?', 'Team appointment, competence, terms of reference, HACCP study boundaries and meeting records.', 'haccp', 'all', ['HACCP' => ['HACCP Step 1', 'Assemble HACCP team and define scope']]),
            $this->r('QSI-HACCP-STEP02', 'Product description', 'Does each HACCP study contain sufficient current information about product, ingredients, processing, packaging, storage, shelf life and distribution?', 'Approved product descriptions, specifications, recipes, packaging, shelf-life basis, storage and distribution information.', 'haccp', 'all', ['HACCP' => ['HACCP Step 2', 'Describe product']]),
            $this->r('QSI-HACCP-STEP03', 'Intended use and consumers', 'Are intended use, reasonably expected handling and vulnerable consumer groups considered in the HACCP study?', 'Intended-use statement, preparation/handling instructions, consumer group assessment and label information.', 'haccp', 'all', ['HACCP' => ['HACCP Step 3', 'Identify intended use and consumers']]),
            $this->r('QSI-HACCP-STEP04', 'Process flow diagram', 'Does the flow diagram accurately represent all relevant process steps, inputs, rework, delays and outsourced activities?', 'Approved flow diagram, site/process comparison, rework/return flows, outsourced steps and revision control.', 'haccp', 'all', ['HACCP' => ['HACCP Step 4', 'Construct flow diagram']]),
            $this->r('QSI-HACCP-STEP05', 'On-site flow confirmation', 'Has the HACCP team confirmed the flow diagram on site across relevant operating conditions and recorded required corrections?', 'Dated on-site confirmation, team participants, shift/product coverage, observations and approved flow revisions.', 'haccp', 'all', ['HACCP' => ['HACCP Step 5', 'On-site confirmation of flow diagram']]),
            $this->r('QSI-HACCP-P02', 'Critical control points', 'Are CCPs determined using a consistent method and supported by clear rationale for significant hazards?', 'CCP determination records, decision tool/rationale, hazard links and food safety team approval.', 'haccp', 'all', ['HACCP' => ['HACCP Principle 2', 'Determine critical control points']]),
            $this->r('QSI-HACCP-P03', 'Validated critical limits', 'Are measurable or observable critical limits established and supported by a valid scientific, regulatory or technical basis?', 'Critical limits, legal/specification basis, validation studies, expert references and approval records.', 'haccp', 'all', ['HACCP' => ['HACCP Principle 3', 'Establish validated critical limits']]),
            $this->r('QSI-HACCP-P04', 'CCP monitoring', 'Does monitoring reliably detect loss of CCP control in time for product and process action, with clear method, frequency and responsibility?', 'Monitoring procedure, records, equipment checks, frequency rationale, responsible personnel and supervisor review.', 'haccp', 'all', ['HACCP' => ['HACCP Principle 4', 'Establish CCP monitoring']]),
            $this->r('QSI-HACCP-P05', 'CCP corrective action', 'Are predetermined actions used when critical limits are not met, including restoration of control and safe disposition of affected product?', 'Deviation records, immediate correction, product hold/evaluation/disposition, root cause, corrective action and authorization.', 'haccp', 'all', ['HACCP' => ['HACCP Principle 5', 'Establish corrective actions']]),
            $this->r('QSI-HACCP-P06', 'HACCP validation and verification', 'Are the HACCP plan and its controls validated before use and verified at planned intervals and after relevant changes?', 'Initial/revalidation evidence, record review, observation, sampling/testing, internal audit, trend analysis and plan review.', 'haccp', 'all', ['HACCP' => ['HACCP Principle 6', 'Validate the plan and establish verification']]),
            $this->r('QSI-HACCP-P07', 'HACCP documents and records', 'Do HACCP documents and records demonstrate consistent implementation, review, retention and traceability of control decisions?', 'Approved HACCP plan, monitoring/deviation/verification records, revision history, retention and retrieval samples.', 'haccp', 'all', ['HACCP' => ['HACCP Principle 7', 'Establish documentation and records']]),
        ];
    }

    private function r(string $code, string $title, string $question, string $evidence, string $family, string $stages, array $mappings): array
    {
        return compact('code', 'title', 'question', 'evidence', 'family', 'stages', 'mappings');
    }
}
