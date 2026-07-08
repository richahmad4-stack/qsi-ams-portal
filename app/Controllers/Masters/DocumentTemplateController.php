<?php

namespace App\Controllers\Masters;

use App\Controllers\BaseController;
use App\Models\DocumentTemplateModel;
use App\Models\DocumentTemplateVersionModel;
use App\Services\AuditLogger;

class DocumentTemplateController extends BaseController
{
    private DocumentTemplateModel $templates;
    private DocumentTemplateVersionModel $versions;
    private AuditLogger $auditLogger;

    public function __construct()
    {
        $this->templates = new DocumentTemplateModel();
        $this->versions = new DocumentTemplateVersionModel();
        $this->auditLogger = new AuditLogger();
    }

    public function index()
    {
        $tenantId = (int) session()->get('tenant_id');

        return view('masters/templates/index', [
            'title' => 'Document Templates',
            'pageTitle' => 'Document Templates',
            'pageSubtitle' => 'PDF and controlled document template library',
            'templates' => $this->templates
                ->where('tenant_id', $tenantId)
                ->orderBy('name', 'ASC')
                ->findAll(),
        ]);
    }

    public function edit(int $id)
    {
        $tenantId = (int) session()->get('tenant_id');
        $template = $this->templates->find($id);

        if ($template === null || (int) $template['tenant_id'] !== $tenantId) {
            return redirect()->to('/masters/templates')->with('error', 'Template not found.');
        }

        return view('masters/templates/form', [
            'title' => 'Edit Template',
            'pageTitle' => 'Edit Template',
            'pageSubtitle' => $template['name'],
            'template' => $template,
            'version' => $this->activeVersion($template),
        ]);
    }

    public function update(int $id)
    {
        $tenantId = (int) session()->get('tenant_id');
        $template = $this->templates->find($id);

        if ($template === null || (int) $template['tenant_id'] !== $tenantId) {
            return redirect()->to('/masters/templates')->with('error', 'Template not found.');
        }

        if (! $this->validate([
            'name' => 'required|max_length[180]',
            'status' => 'required|max_length[40]',
            'document_number' => 'permit_empty|max_length[40]',
            'revision_number' => 'permit_empty|max_length[20]',
            'issue_number' => 'permit_empty|max_length[20]',
            'document_date' => 'permit_empty|valid_date[Y-m-d]',
            'body_html' => 'required',
        ])) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $templatePayload = [
            'name' => (string) $this->request->getPost('name'),
            'status' => (string) $this->request->getPost('status'),
            'document_number' => $this->nullableText('document_number'),
            'revision_number' => $this->nullableText('revision_number'),
            'issue_number' => $this->nullableText('issue_number'),
            'document_date' => $this->nullableText('document_date'),
        ];
        $this->templates->update($id, $templatePayload);

        $latestVersion = $this->versions
            ->where('document_template_id', $id)
            ->orderBy('version_number', 'DESC')
            ->first();
        $versionNumber = (int) ($latestVersion['version_number'] ?? 0) + 1;
        $versionPayload = [
            'document_template_id' => $id,
            'version_number' => $versionNumber,
            'body_html' => (string) $this->request->getPost('body_html'),
            'header_html' => $this->nullableText('header_html'),
            'footer_html' => $this->nullableText('footer_html'),
            'created_by' => (int) session()->get('user_id'),
            'approved_by' => (int) session()->get('user_id'),
            'approved_at' => date('Y-m-d H:i:s'),
        ];

        $versionId = (int) $this->versions->insert($versionPayload);
        $this->templates->update($id, ['active_version' => $versionNumber]);
        $this->auditLogger->record('update', 'document_templates', 'document_templates', $id, $template, $templatePayload + ['version_id' => $versionId]);

        return redirect()->to('/masters/templates')->with('success', 'Template version saved.');
    }

    private function activeVersion(array $template): ?array
    {
        if ($template['active_version'] === null) {
            return $this->versions
                ->where('document_template_id', (int) $template['id'])
                ->orderBy('version_number', 'DESC')
                ->first();
        }

        return $this->versions
            ->where('document_template_id', (int) $template['id'])
            ->where('version_number', (int) $template['active_version'])
            ->first();
    }

    private function nullableText(string $field): ?string
    {
        $value = trim((string) $this->request->getPost($field));

        return $value === '' ? null : $value;
    }
}
