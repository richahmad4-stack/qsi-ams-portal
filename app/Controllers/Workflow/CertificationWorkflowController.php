<?php

namespace App\Controllers\Workflow;

use App\Controllers\BaseController;
use App\Models\ClientModel;
use App\Services\AuditReportNarrativeService;
use App\Services\CertificationWorkflowService;
use Config\Database;

class CertificationWorkflowController extends BaseController
{
    private CertificationWorkflowService $workflow;
    private ClientModel $clients;
    private AuditReportNarrativeService $narratives;

    public function __construct()
    {
        $this->workflow = new CertificationWorkflowService();
        $this->clients = new ClientModel();
        $this->narratives = new AuditReportNarrativeService();
    }

    public function index()
    {
        $tenantId = (int) session()->get('tenant_id');

        return view('workflow/index', [
            'title' => 'Certification Workflow',
            'pageTitle' => 'Certification Workflow',
            'pageSubtitle' => 'Application to feedback workflow tracker',
            'summaries' => $this->workflow->clientSummaries($tenantId),
        ]);
    }

    public function show(int $clientId)
    {
        $tenantId = (int) session()->get('tenant_id');
        $client = $this->clients->find($clientId);

        if ($client === null || (int) $client['tenant_id'] !== $tenantId) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        $this->ensureClientAuditChecklists($tenantId, $clientId);

        return view('workflow/show', [
            'title' => 'Client Workflow',
            'pageTitle' => $client['company'],
            'pageSubtitle' => 'Certification workflow status',
            'client' => $client,
            'workflow' => $this->workflow->buildForClient($tenantId, $clientId),
        ]);
    }

    private function ensureClientAuditChecklists(int $tenantId, int $clientId): void
    {
        $db = Database::connect();
        $program = $db->table('audit_programs')
            ->where('tenant_id', $tenantId)
            ->where('client_id', $clientId)
            ->orderBy('id', 'DESC')
            ->get(1)
            ->getRowArray();

        if ($program === null) {
            return;
        }

        $clauses = $db->table('clause_library')
            ->select('clause_library.*, standards.code AS standard_code')
            ->join('standards', 'standards.id = clause_library.standard_id')
            ->join('client_standards', 'client_standards.standard_id = clause_library.standard_id')
            ->where('clause_library.tenant_id', $tenantId)
            ->where('client_standards.client_id', $clientId)
            ->where('clause_library.active', 1)
            ->orderBy('standards.code', 'ASC')
            ->orderBy('clause_library.clause_number', 'ASC')
            ->get()
            ->getResultArray();

        if ($clauses === []) {
            return;
        }

        $events = $db->table('audit_events')
            ->where('audit_program_id', (int) $program['id'])
            ->get()
            ->getResultArray();

        foreach ($events as $event) {
            $report = $db->table('report_drafts')
                ->where('tenant_id', $tenantId)
                ->where('audit_event_id', (int) $event['id'])
                ->where('report_type', 'audit_execution')
                ->get(1)
                ->getRowArray();

            if ($report === null) {
                $db->table('report_drafts')->insert([
                    'tenant_id' => $tenantId,
                    'audit_event_id' => (int) $event['id'],
                    'report_type' => 'audit_execution',
                    'version_number' => 1,
                    'status' => 'draft',
                    'generated_payload' => json_encode(['source' => 'client_file_tabs'], JSON_THROW_ON_ERROR),
                    'editable_payload' => json_encode([], JSON_THROW_ON_ERROR),
                    'prepared_by' => (int) session()->get('user_id'),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $report = ['id' => $db->insertID()];
            }

            foreach ($clauses as $index => $clause) {
                $exists = $db->table('report_sections')
                    ->where('report_draft_id', (int) $report['id'])
                    ->where('clause_library_id', (int) $clause['id'])
                    ->where('section_key', 'conformity')
                    ->countAllResults();

                if ($exists > 0) {
                    continue;
                }

                $planItems = $db->table('audit_plan_items')
                    ->select('audit_plan_items.*, personnel.full_name AS auditor_name')
                    ->join('audit_plans', 'audit_plans.id = audit_plan_items.audit_plan_id')
                    ->join('personnel', 'personnel.id = audit_plan_items.auditor_personnel_id', 'left')
                    ->where('audit_plans.audit_event_id', (int) $event['id'])
                    ->get()
                    ->getResultArray();
                $auditTeam = $db->table('auditor_appointments')
                    ->select('auditor_appointments.*, personnel.full_name')
                    ->join('personnel', 'personnel.id = auditor_appointments.personnel_id')
                    ->where('auditor_appointments.audit_event_id', (int) $event['id'])
                    ->get()
                    ->getResultArray();

                $db->table('report_sections')->insert([
                    'report_draft_id' => (int) $report['id'],
                    'clause_library_id' => (int) $clause['id'],
                    'section_key' => 'conformity',
                    'section_title' => trim((string) $clause['standard_code'] . ' ' . (string) $clause['clause_number'] . ' - ' . (string) $clause['clause_title']),
                    'section_content' => $this->narratives->conformityNote($client, $event, $clause, $planItems, $auditTeam),
                    'sort_order' => $index + 1,
                ]);
            }
        }
    }
}
