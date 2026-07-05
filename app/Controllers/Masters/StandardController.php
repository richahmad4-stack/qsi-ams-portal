<?php

namespace App\Controllers\Masters;

use App\Controllers\BaseController;
use App\Models\StandardModel;
use App\Services\AuditLogger;

class StandardController extends BaseController
{
    private StandardModel $standards;
    private AuditLogger $auditLogger;

    public function __construct()
    {
        $this->standards = new StandardModel();
        $this->auditLogger = new AuditLogger();
    }

    public function index()
    {
        return view('masters/standards/index', [
            'title' => 'Standards',
            'pageTitle' => 'Standards',
            'pageSubtitle' => 'Certification schemes and standard versions',
            'standards' => $this->standards->orderBy('code', 'ASC')->findAll(),
        ]);
    }

    public function new()
    {
        return view('masters/standards/form', [
            'title' => 'New Standard',
            'pageTitle' => 'New Standard',
            'pageSubtitle' => 'Add a certification standard',
            'standard' => $this->blankStandard(),
            'action' => site_url('masters/standards'),
        ]);
    }

    public function create()
    {
        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = $this->payload();
        $id = (int) $this->standards->insert($data);
        $this->auditLogger->record('create', 'standards', 'standards', $id, null, $data);

        return redirect()->to('/masters/standards')->with('success', 'Standard created.');
    }

    public function edit(int $id)
    {
        $standard = $this->standards->find($id);

        if ($standard === null) {
            return redirect()->to('/masters/standards')->with('error', 'Standard not found.');
        }

        return view('masters/standards/form', [
            'title' => 'Edit Standard',
            'pageTitle' => 'Edit Standard',
            'pageSubtitle' => $standard['code'],
            'standard' => $standard,
            'action' => site_url('masters/standards/' . $id),
        ]);
    }

    public function update(int $id)
    {
        $standard = $this->standards->find($id);

        if ($standard === null) {
            return redirect()->to('/masters/standards')->with('error', 'Standard not found.');
        }

        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = $this->payload();
        $this->standards->update($id, $data);
        $this->auditLogger->record('update', 'standards', 'standards', $id, $standard, $data);

        return redirect()->to('/masters/standards')->with('success', 'Standard updated.');
    }

    public function deactivate(int $id)
    {
        $standard = $this->standards->find($id);

        if ($standard === null) {
            return redirect()->to('/masters/standards')->with('error', 'Standard not found.');
        }

        $this->standards->update($id, ['active' => 0]);
        $this->auditLogger->record('delete', 'standards', 'standards', $id, $standard, ['active' => 0]);

        return redirect()->to('/masters/standards')->with('success', 'Standard deactivated.');
    }

    private function rules(): array
    {
        return [
            'code' => 'required|max_length[80]',
            'name' => 'required|max_length[180]',
            'version' => 'permit_empty|max_length[80]',
            'scheme_type' => 'permit_empty|max_length[80]',
        ];
    }

    private function payload(): array
    {
        return [
            'code' => trim((string) $this->request->getPost('code')),
            'name' => trim((string) $this->request->getPost('name')),
            'version' => trim((string) $this->request->getPost('version')) ?: null,
            'scheme_type' => trim((string) $this->request->getPost('scheme_type')) ?: null,
            'active' => $this->request->getPost('active') === '1' ? 1 : 0,
        ];
    }

    private function blankStandard(): array
    {
        return [
            'code' => '',
            'name' => '',
            'version' => '',
            'scheme_type' => '',
            'active' => 1,
        ];
    }
}
