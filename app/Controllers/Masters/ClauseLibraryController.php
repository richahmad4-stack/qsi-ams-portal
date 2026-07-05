<?php

namespace App\Controllers\Masters;

use App\Controllers\BaseController;
use App\Models\ClauseLibraryModel;
use App\Models\StandardModel;
use App\Services\AuditLogger;

class ClauseLibraryController extends BaseController
{
    private ClauseLibraryModel $clauses;
    private StandardModel $standards;
    private AuditLogger $auditLogger;

    public function __construct()
    {
        $this->clauses = new ClauseLibraryModel();
        $this->standards = new StandardModel();
        $this->auditLogger = new AuditLogger();
    }

    public function index()
    {
        $tenantId = (int) session()->get('tenant_id');

        return view('masters/clauses/index', [
            'title' => 'Clause Library',
            'pageTitle' => 'Clause Library',
            'pageSubtitle' => 'Predefined audit notes, evidence and guidance by clause',
            'clauses' => $this->clauses
                ->select('clause_library.*, standards.code AS standard_code')
                ->join('standards', 'standards.id = clause_library.standard_id')
                ->where('clause_library.tenant_id', $tenantId)
                ->orderBy('standards.code', 'ASC')
                ->orderBy('clause_library.clause_number', 'ASC')
                ->findAll(),
        ]);
    }

    public function new()
    {
        return view('masters/clauses/form', [
            'title' => 'New Clause',
            'pageTitle' => 'New Clause',
            'pageSubtitle' => 'Create reusable audit content',
            'clause' => $this->blank(),
            'standards' => $this->activeStandards(),
            'action' => site_url('masters/clauses'),
        ]);
    }

    public function create()
    {
        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = $this->payload();
        $data['tenant_id'] = (int) session()->get('tenant_id');

        $id = (int) $this->clauses->insert($data);
        $this->auditLogger->record('create', 'clause_library', 'clause_library', $id, null, $data);

        return redirect()->to('/masters/clauses')->with('success', 'Clause created.');
    }

    public function edit(int $id)
    {
        $clause = $this->findTenantClause($id);

        if ($clause === null) {
            return redirect()->to('/masters/clauses')->with('error', 'Clause not found.');
        }

        return view('masters/clauses/form', [
            'title' => 'Edit Clause',
            'pageTitle' => 'Edit Clause',
            'pageSubtitle' => $clause['clause_number'] . ' ' . $clause['clause_title'],
            'clause' => $clause,
            'standards' => $this->activeStandards(),
            'action' => site_url('masters/clauses/' . $id),
        ]);
    }

    public function update(int $id)
    {
        $clause = $this->findTenantClause($id);

        if ($clause === null) {
            return redirect()->to('/masters/clauses')->with('error', 'Clause not found.');
        }

        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = $this->payload();
        $this->clauses->update($id, $data);
        $this->auditLogger->record('update', 'clause_library', 'clause_library', $id, $clause, $data);

        return redirect()->to('/masters/clauses')->with('success', 'Clause updated.');
    }

    public function deactivate(int $id)
    {
        $clause = $this->findTenantClause($id);

        if ($clause === null) {
            return redirect()->to('/masters/clauses')->with('error', 'Clause not found.');
        }

        $this->clauses->update($id, ['active' => 0]);
        $this->auditLogger->record('delete', 'clause_library', 'clause_library', $id, $clause, ['active' => 0]);

        return redirect()->to('/masters/clauses')->with('success', 'Clause deactivated.');
    }

    private function findTenantClause(int $id): ?array
    {
        $clause = $this->clauses->find($id);

        if ($clause === null || (int) $clause['tenant_id'] !== (int) session()->get('tenant_id')) {
            return null;
        }

        return $clause;
    }

    private function activeStandards(): array
    {
        return $this->standards->where('active', 1)->orderBy('code', 'ASC')->findAll();
    }

    private function rules(): array
    {
        return [
            'standard_id' => 'required|integer',
            'clause_number' => 'required|max_length[60]',
            'clause_title' => 'required|max_length[255]',
            'requirement' => 'required',
        ];
    }

    private function payload(): array
    {
        return [
            'standard_id' => (int) $this->request->getPost('standard_id'),
            'clause_number' => trim((string) $this->request->getPost('clause_number')),
            'clause_title' => trim((string) $this->request->getPost('clause_title')),
            'requirement' => trim((string) $this->request->getPost('requirement')),
            'predefined_conformity_note' => trim((string) $this->request->getPost('predefined_conformity_note')) ?: null,
            'positive_finding' => trim((string) $this->request->getPost('positive_finding')) ?: null,
            'opportunity_for_improvement' => trim((string) $this->request->getPost('opportunity_for_improvement')) ?: null,
            'minor_nc' => trim((string) $this->request->getPost('minor_nc')) ?: null,
            'major_nc' => trim((string) $this->request->getPost('major_nc')) ?: null,
            'evidence_examples' => trim((string) $this->request->getPost('evidence_examples')) ?: null,
            'auditor_guidance' => trim((string) $this->request->getPost('auditor_guidance')) ?: null,
            'risk_rating' => trim((string) $this->request->getPost('risk_rating')) ?: null,
            'stage_applicability' => trim((string) $this->request->getPost('stage_applicability')) ?: null,
            'active' => $this->request->getPost('active') === '1' ? 1 : 0,
        ];
    }

    private function blank(): array
    {
        return [
            'standard_id' => '',
            'clause_number' => '',
            'clause_title' => '',
            'requirement' => '',
            'predefined_conformity_note' => '',
            'positive_finding' => '',
            'opportunity_for_improvement' => '',
            'minor_nc' => '',
            'major_nc' => '',
            'evidence_examples' => '',
            'auditor_guidance' => '',
            'risk_rating' => '',
            'stage_applicability' => '',
            'active' => 1,
        ];
    }
}
