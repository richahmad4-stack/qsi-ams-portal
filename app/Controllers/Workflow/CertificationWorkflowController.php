<?php

namespace App\Controllers\Workflow;

use App\Controllers\BaseController;
use App\Models\ClientModel;
use App\Services\CertificationWorkflowService;
use Config\Database;

class CertificationWorkflowController extends BaseController
{
    private CertificationWorkflowService $workflow;
    private ClientModel $clients;

    public function __construct()
    {
        $this->workflow = new CertificationWorkflowService();
        $this->clients = new ClientModel();
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

        return view('workflow/show', [
            'title' => 'Client Workflow',
            'pageTitle' => $client['company'],
            'pageSubtitle' => 'Certification workflow status',
            'client' => $client,
            'workflow' => $this->workflow->buildForClient($tenantId, $clientId),
            'documentControls' => $this->documentControls($tenantId),
        ]);
    }

    private function documentControls(int $tenantId): array
    {
        $rows = Database::connect()->table('document_templates')
            ->select('id, template_key, name, document_number, revision_number, issue_number, document_date')
            ->where('tenant_id', $tenantId)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $controls = [];
        foreach ($rows as $row) {
            $controls[(string) $row['template_key']] = $row;
        }

        return $controls;
    }

}
