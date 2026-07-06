<?php

namespace App\Controllers\Masters;

use App\Controllers\BaseController;
use App\Models\ClauseContentPoolModel;
use App\Models\ClauseLibraryModel;
use App\Models\StandardModel;
use App\Services\AuditLogger;

class ClauseContentPoolController extends BaseController
{
    private ClauseContentPoolModel $pool;
    private StandardModel $standards;
    private ClauseLibraryModel $clauses;
    private AuditLogger $auditLogger;

    public function __construct()
    {
        $this->pool = new ClauseContentPoolModel();
        $this->standards = new StandardModel();
        $this->clauses = new ClauseLibraryModel();
        $this->auditLogger = new AuditLogger();
    }

    public function index()
    {
        $tenantId = (int) session()->get('tenant_id');
        $filters = [
            'standard_id' => (int) ($this->request->getGet('standard_id') ?? 0),
            'content_type' => trim((string) ($this->request->getGet('content_type') ?? '')),
            'active' => trim((string) ($this->request->getGet('active') ?? '')),
            'scope' => trim((string) ($this->request->getGet('scope') ?? '')),
        ];

        $builder = $this->pool
            ->select('clause_content_pool.*, standards.code AS standard_code, clause_library.clause_number, clause_library.clause_title')
            ->join('standards', 'standards.id = clause_content_pool.standard_id', 'left')
            ->join('clause_library', 'clause_library.id = clause_content_pool.clause_library_id', 'left')
            ->where('clause_content_pool.tenant_id', $tenantId);

        if ($filters['standard_id'] > 0) {
            $builder->where('clause_content_pool.standard_id', $filters['standard_id']);
        }
        if ($filters['content_type'] !== '') {
            $builder->where('clause_content_pool.content_type', $filters['content_type']);
        }
        if ($filters['active'] !== '') {
            $builder->where('clause_content_pool.active', $filters['active'] === '1' ? 1 : 0);
        }
        if ($filters['scope'] !== '') {
            $builder->like('clause_content_pool.scope_keyword', $filters['scope']);
        }

        return view('masters/clause_pool/index', [
            'title' => 'Content Library / Clause Pool',
            'pageTitle' => 'Content Library / Clause Pool',
            'pageSubtitle' => 'Approved reusable audit answers, evidence, NC and CAPA templates',
            'rows' => $builder->orderBy('standards.code', 'ASC')->orderBy('clause_content_pool.content_type', 'ASC')->findAll(),
            'standards' => $this->activeStandards(),
            'filters' => $filters,
        ]);
    }

    public function new()
    {
        return view('masters/clause_pool/form', [
            'title' => 'New Clause Pool Template',
            'pageTitle' => 'New Clause Pool Template',
            'pageSubtitle' => 'Create approved reusable content',
            'row' => $this->blank(),
            'standards' => $this->activeStandards(),
            'clauses' => $this->tenantClauses(),
            'action' => site_url('masters/clause-pool'),
        ]);
    }

    public function create()
    {
        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = $this->payload();
        $data['tenant_id'] = (int) session()->get('tenant_id');
        $id = (int) $this->pool->insert($data);
        $this->auditLogger->record('create', 'clause_pool', 'clause_content_pool', $id, null, $data);

        return redirect()->to('/masters/clause-pool')->with('success', 'Clause Pool template created.');
    }

    public function edit(int $id)
    {
        $row = $this->tenantRow($id);
        if ($row === null) {
            return redirect()->to('/masters/clause-pool')->with('error', 'Template not found.');
        }

        return view('masters/clause_pool/form', [
            'title' => 'Edit Clause Pool Template',
            'pageTitle' => 'Edit Clause Pool Template',
            'pageSubtitle' => $row['template_title'],
            'row' => $row,
            'standards' => $this->activeStandards(),
            'clauses' => $this->tenantClauses(),
            'action' => site_url('masters/clause-pool/' . $id),
        ]);
    }

    public function update(int $id)
    {
        $row = $this->tenantRow($id);
        if ($row === null) {
            return redirect()->to('/masters/clause-pool')->with('error', 'Template not found.');
        }
        if (! $this->validate($this->rules($id))) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = $this->payload();
        $this->pool->update($id, $data);
        $this->auditLogger->record('update', 'clause_pool', 'clause_content_pool', $id, $row, $data);

        return redirect()->to('/masters/clause-pool')->with('success', 'Clause Pool template updated.');
    }

    public function deactivate(int $id)
    {
        $row = $this->tenantRow($id);
        if ($row === null) {
            return redirect()->to('/masters/clause-pool')->with('error', 'Template not found.');
        }

        $this->pool->update($id, ['active' => 0]);
        $this->auditLogger->record('delete', 'clause_pool', 'clause_content_pool', $id, $row, ['active' => 0]);

        return redirect()->to('/masters/clause-pool')->with('success', 'Template deactivated.');
    }

    public function export()
    {
        $tenantId = (int) session()->get('tenant_id');
        $rows = $this->pool->where('tenant_id', $tenantId)->orderBy('template_code')->findAll();
        $filename = 'clause-pool-' . date('Ymd-His') . '.csv';
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['template_code', 'standard_id', 'clause_library_id', 'scope_keyword', 'industry_type', 'audit_stage', 'content_type', 'severity', 'template_title', 'content_text', 'active']);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['template_code'],
                $row['standard_id'],
                $row['clause_library_id'],
                $row['scope_keyword'],
                $row['industry_type'],
                $row['audit_stage'],
                $row['content_type'],
                $row['severity'],
                $row['template_title'],
                $row['content_text'],
                $row['active'],
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $this->response
            ->setHeader('Content-Type', 'text/csv')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csv);
    }

    public function import()
    {
        $file = $this->request->getFile('pool_file');
        if ($file === null || ! $file->isValid()) {
            return redirect()->back()->with('error', 'Upload a valid CSV file.');
        }

        $handle = fopen($file->getTempName(), 'r');
        if ($handle === false) {
            return redirect()->back()->with('error', 'Unable to read uploaded file.');
        }

        $headers = fgetcsv($handle) ?: [];
        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);
            if (! is_array($data) || trim((string) ($data['template_code'] ?? '')) === '') {
                continue;
            }

            $payload = [
                'tenant_id' => (int) session()->get('tenant_id'),
                'standard_id' => $this->intOrNull($data['standard_id'] ?? null),
                'clause_library_id' => $this->intOrNull($data['clause_library_id'] ?? null),
                'scope_keyword' => trim((string) ($data['scope_keyword'] ?? '')) ?: null,
                'industry_type' => trim((string) ($data['industry_type'] ?? '')) ?: null,
                'audit_stage' => trim((string) ($data['audit_stage'] ?? 'all')) ?: 'all',
                'content_type' => trim((string) ($data['content_type'] ?? 'conformity_answer')),
                'severity' => trim((string) ($data['severity'] ?? '')) ?: null,
                'template_code' => trim((string) $data['template_code']),
                'template_title' => trim((string) ($data['template_title'] ?? $data['template_code'])),
                'content_text' => trim((string) ($data['content_text'] ?? '')),
                'active' => (int) ($data['active'] ?? 1) === 1 ? 1 : 0,
            ];
            if ($payload['content_text'] === '') {
                continue;
            }

            $existing = $this->pool->where('tenant_id', $payload['tenant_id'])->where('template_code', $payload['template_code'])->first();
            if ($existing === null) {
                $this->pool->insert($payload);
            } else {
                $this->pool->update((int) $existing['id'], $payload);
            }
            $count++;
        }
        fclose($handle);

        return redirect()->to('/masters/clause-pool')->with('success', $count . ' template row(s) imported.');
    }

    private function tenantRow(int $id): ?array
    {
        $row = $this->pool->find($id);
        return $row !== null && (int) $row['tenant_id'] === (int) session()->get('tenant_id') ? $row : null;
    }

    private function activeStandards(): array
    {
        return $this->standards->where('active', 1)->orderBy('code', 'ASC')->findAll();
    }

    private function tenantClauses(): array
    {
        return $this->clauses
            ->select('clause_library.id, clause_library.standard_id, clause_library.clause_number, clause_library.clause_title, standards.code AS standard_code')
            ->join('standards', 'standards.id = clause_library.standard_id')
            ->where('clause_library.tenant_id', (int) session()->get('tenant_id'))
            ->where('clause_library.active', 1)
            ->orderBy('standards.code', 'ASC')
            ->orderBy('clause_library.clause_number', 'ASC')
            ->findAll();
    }

    private function rules(?int $id = null): array
    {
        return [
            'template_code' => 'required|max_length[80]',
            'template_title' => 'required|max_length[180]',
            'content_type' => 'required|max_length[60]',
            'content_text' => 'required',
        ];
    }

    private function payload(): array
    {
        return [
            'standard_id' => $this->intOrNull($this->request->getPost('standard_id')),
            'clause_library_id' => $this->intOrNull($this->request->getPost('clause_library_id')),
            'scope_keyword' => trim((string) $this->request->getPost('scope_keyword')) ?: null,
            'industry_type' => trim((string) $this->request->getPost('industry_type')) ?: null,
            'audit_stage' => trim((string) $this->request->getPost('audit_stage')) ?: 'all',
            'content_type' => trim((string) $this->request->getPost('content_type')),
            'severity' => trim((string) $this->request->getPost('severity')) ?: null,
            'template_code' => trim((string) $this->request->getPost('template_code')),
            'template_title' => trim((string) $this->request->getPost('template_title')),
            'content_text' => trim((string) $this->request->getPost('content_text')),
            'tags' => json_encode(array_filter(array_map('trim', explode(',', (string) $this->request->getPost('tags')))), JSON_THROW_ON_ERROR),
            'active' => $this->request->getPost('active') === '1' ? 1 : 0,
        ];
    }

    private function blank(): array
    {
        return [
            'standard_id' => '',
            'clause_library_id' => '',
            'scope_keyword' => '',
            'industry_type' => '',
            'audit_stage' => 'all',
            'content_type' => 'conformity_answer',
            'severity' => '',
            'template_code' => '',
            'template_title' => '',
            'content_text' => '',
            'tags' => '',
            'active' => 1,
        ];
    }

    private function intOrNull(mixed $value): ?int
    {
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }
}
