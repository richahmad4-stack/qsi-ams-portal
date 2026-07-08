<?php

namespace App\Controllers\Masters;

use App\Controllers\BaseController;
use App\Models\ClientAttachmentModel;
use App\Models\ClientModel;
use App\Models\ClientProcessModel;
use App\Models\ClientSiteModel;
use App\Models\ClientStandardModel;
use App\Models\FoodChainCategoryModel;
use App\Models\IafCodeModel;
use App\Models\MedicalDeviceCategoryModel;
use App\Models\NaceCodeModel;
use App\Models\StandardModel;
use App\Services\AuditLogger;

class ClientController extends BaseController
{
    private ClientModel $clients;
    private ClientStandardModel $clientStandards;
    private ClientSiteModel $clientSites;
    private ClientProcessModel $clientProcesses;
    private ClientAttachmentModel $clientAttachments;
    private AuditLogger $auditLogger;

    public function __construct()
    {
        $this->clients = new ClientModel();
        $this->clientStandards = new ClientStandardModel();
        $this->clientSites = new ClientSiteModel();
        $this->clientProcesses = new ClientProcessModel();
        $this->clientAttachments = new ClientAttachmentModel();
        $this->auditLogger = new AuditLogger();
    }

    public function index()
    {
        $tenantId = (int) session()->get('tenant_id');

        return view('masters/clients/index', [
            'title' => 'Clients',
            'pageTitle' => 'Client Master',
            'pageSubtitle' => 'Certification clients and lifecycle status',
            'clients' => $this->clients
                ->select('clients.*, GROUP_CONCAT(standards.code ORDER BY standards.code SEPARATOR ", ") AS requested_standards')
                ->join('client_standards', 'client_standards.client_id = clients.id', 'left')
                ->join('standards', 'standards.id = client_standards.standard_id', 'left')
                ->where('tenant_id', $tenantId)
                ->groupBy('clients.id')
                ->orderBy('company', 'ASC')
                ->findAll(),
        ]);
    }

    public function new()
    {
        return view('masters/clients/form', [
            'title' => 'New Client',
            'pageTitle' => 'New Client',
            'pageSubtitle' => 'Create a certification client profile',
            'client' => $this->blankClient(),
            'standards' => $this->activeStandards(),
            'selectedStandardIds' => [],
            'action' => site_url('masters/clients'),
            'method' => 'post',
        ]);
    }

    public function create()
    {
        $data = $this->clientPayload();
        $data['tenant_id'] = (int) session()->get('tenant_id');
        $data['created_by'] = (int) session()->get('user_id');

        if (! $this->validateLogoUpload()) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $id = (int) $this->clients->insert($data);
        $logoPath = $this->saveClientLogo($id);
        if ($logoPath !== null) {
            $this->clients->update($id, ['client_logo_path' => $logoPath]);
            $data['client_logo_path'] = $logoPath;
        }
        $this->syncRequestedStandards($id);
        $this->auditLogger->record('create', 'clients', 'clients', $id, null, $data);

        return redirect()->to('/masters/clients/' . $id)->with('success', 'Client created.');
    }

    public function edit(int $id)
    {
        $client = $this->findTenantClient($id);

        if ($client === null) {
            return redirect()->to('/masters/clients')->with('error', 'Client not found.');
        }

        return view('masters/clients/form', [
            'title' => 'Edit Client',
            'pageTitle' => 'Edit Client',
            'pageSubtitle' => $client['company'],
            'client' => $client,
            'standards' => $this->activeStandards(),
            'selectedStandardIds' => $this->selectedStandardIds($id),
            'action' => site_url('masters/clients/' . $id),
            'method' => 'post',
        ]);
    }

    public function show(int $id)
    {
        $client = $this->findTenantClient($id);

        if ($client === null) {
            return redirect()->to('/masters/clients')->with('error', 'Client not found.');
        }

        return view('masters/clients/show', [
            'title' => 'Client Details',
            'pageTitle' => $client['company'],
            'pageSubtitle' => 'Client standards, sites, processes and evidence',
            'client' => $client,
            'clientStandards' => $this->clientStandards
                ->select('client_standards.*, standards.code AS standard_code, iaf_codes.code AS iaf_code, nace_codes.code AS nace_code, food_chain_categories.code AS food_code, medical_device_categories.code AS medical_code')
                ->join('standards', 'standards.id = client_standards.standard_id')
                ->join('iaf_codes', 'iaf_codes.id = client_standards.iaf_code_id', 'left')
                ->join('nace_codes', 'nace_codes.id = client_standards.nace_code_id', 'left')
                ->join('food_chain_categories', 'food_chain_categories.id = client_standards.food_chain_category_id', 'left')
                ->join('medical_device_categories', 'medical_device_categories.id = client_standards.medical_device_category_id', 'left')
                ->where('client_standards.client_id', $id)
                ->orderBy('standards.code', 'ASC')
                ->findAll(),
            'sites' => $this->clientSites->where('client_id', $id)->orderBy('site_name', 'ASC')->findAll(),
            'processes' => $this->clientProcesses->where('client_id', $id)->orderBy('process_name', 'ASC')->findAll(),
            'attachments' => $this->clientAttachments->where('client_id', $id)->orderBy('created_at', 'DESC')->findAll(),
            'standards' => (new StandardModel())->where('active', 1)->orderBy('code', 'ASC')->findAll(),
            'iafCodes' => (new IafCodeModel())->where('active', 1)->orderBy('code', 'ASC')->findAll(),
            'naceCodes' => (new NaceCodeModel())->where('active', 1)->orderBy('code', 'ASC')->findAll(),
            'foodCategories' => (new FoodChainCategoryModel())->where('active', 1)->orderBy('code', 'ASC')->findAll(),
            'medicalCategories' => (new MedicalDeviceCategoryModel())->where('active', 1)->orderBy('code', 'ASC')->findAll(),
        ]);
    }

    public function update(int $id)
    {
        $client = $this->findTenantClient($id);

        if ($client === null) {
            return redirect()->to('/masters/clients')->with('error', 'Client not found.');
        }

        if (! $this->validateLogoUpload()) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = $this->clientPayload();
        $logoPath = $this->saveClientLogo($id);
        if ($logoPath !== null) {
            $data['client_logo_path'] = $logoPath;
        }
        $this->clients->update($id, $data);
        $this->syncRequestedStandards($id);
        $this->auditLogger->record('update', 'clients', 'clients', $id, $client, $data);

        return redirect()->to('/masters/clients/' . $id)->with('success', 'Client updated.');
    }

    public function delete(int $id)
    {
        $client = $this->findTenantClient($id);

        if ($client === null) {
            return redirect()->to('/masters/clients')->with('error', 'Client not found.');
        }

        $this->clients->delete($id);
        $this->auditLogger->record('delete', 'clients', 'clients', $id, $client, null);

        return redirect()->to('/masters/clients')->with('success', 'Client deleted.');
    }

    public function logo(int $id)
    {
        $client = $this->findTenantClient($id);

        if ($client === null) {
            return $this->response->setStatusCode(404);
        }

        $path = $this->clientLogoAbsolutePath((string) ($client['client_logo_path'] ?? ''));
        if ($path === '') {
            return $this->response->setStatusCode(404);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $extension === 'png' ? 'image/png' : 'image/jpeg';

        return $this->response
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setContentType($mime)
            ->setBody((string) file_get_contents($path));
    }

    public function addStandard(int $id)
    {
        $client = $this->findTenantClient($id);

        if ($client === null) {
            return redirect()->to('/masters/clients')->with('error', 'Client not found.');
        }

        if (! $this->validate([
            'standard_id' => 'required|integer',
            'scope' => 'permit_empty',
        ])) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = [
            'client_id' => $id,
            'standard_id' => (int) $this->request->getPost('standard_id'),
            'iaf_code_id' => $this->intOrNull('iaf_code_id'),
            'nace_code_id' => $this->intOrNull('nace_code_id'),
            'food_chain_category_id' => $this->intOrNull('food_chain_category_id'),
            'medical_device_category_id' => $this->intOrNull('medical_device_category_id'),
            'scope' => trim((string) $this->request->getPost('scope')) ?: null,
        ];

        $existing = $this->clientStandards
            ->where('client_id', $id)
            ->where('standard_id', $data['standard_id'])
            ->first();

        if ($existing !== null) {
            return redirect()->to('/masters/clients/' . $id)->with('error', 'This standard is already linked to the client.');
        }

        $recordId = (int) $this->clientStandards->insert($data);
        $this->auditLogger->record('create', 'clients', 'client_standards', $recordId, null, $data);

        return redirect()->to('/masters/clients/' . $id)->with('success', 'Client standard added.');
    }

    public function deleteStandard(int $id, int $recordId)
    {
        return $this->deleteChildRecord($id, $recordId, $this->clientStandards, 'client_standards', 'Client standard removed.');
    }

    public function addSite(int $id)
    {
        $client = $this->findTenantClient($id);

        if ($client === null) {
            return redirect()->to('/masters/clients')->with('error', 'Client not found.');
        }

        if (! $this->validate([
            'site_name' => 'required|max_length[180]',
            'employee_count' => 'permit_empty|integer|greater_than_equal_to[0]',
        ])) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = [
            'client_id' => $id,
            'site_name' => trim((string) $this->request->getPost('site_name')),
            'address' => trim((string) $this->request->getPost('address')) ?: null,
            'country' => trim((string) $this->request->getPost('country')) ?: null,
            'city' => trim((string) $this->request->getPost('city')) ?: null,
            'employee_count' => $this->intOrNull('employee_count'),
            'processes' => trim((string) $this->request->getPost('processes')) ?: null,
            'active' => $this->request->getPost('active') === '1' ? 1 : 0,
        ];

        $recordId = (int) $this->clientSites->insert($data);
        $this->auditLogger->record('create', 'clients', 'client_sites', $recordId, null, $data);

        return redirect()->to('/masters/clients/' . $id)->with('success', 'Client site added.');
    }

    public function deleteSite(int $id, int $recordId)
    {
        return $this->deleteChildRecord($id, $recordId, $this->clientSites, 'client_sites', 'Client site removed.');
    }

    public function addProcess(int $id)
    {
        $client = $this->findTenantClient($id);

        if ($client === null) {
            return redirect()->to('/masters/clients')->with('error', 'Client not found.');
        }

        if (! $this->validate([
            'process_name' => 'required|max_length[180]',
        ])) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = [
            'client_id' => $id,
            'process_name' => trim((string) $this->request->getPost('process_name')),
            'description' => trim((string) $this->request->getPost('description')) ?: null,
        ];

        $recordId = (int) $this->clientProcesses->insert($data);
        $this->auditLogger->record('create', 'clients', 'client_processes', $recordId, null, $data);

        return redirect()->to('/masters/clients/' . $id)->with('success', 'Client process added.');
    }

    public function deleteProcess(int $id, int $recordId)
    {
        return $this->deleteChildRecord($id, $recordId, $this->clientProcesses, 'client_processes', 'Client process removed.');
    }

    public function addAttachment(int $id)
    {
        $client = $this->findTenantClient($id);

        if ($client === null) {
            return redirect()->to('/masters/clients')->with('error', 'Client not found.');
        }

        if (! $this->validate([
            'category' => 'required|max_length[80]',
            'original_filename' => 'required|max_length[255]',
            'storage_path' => 'required|max_length[500]',
        ])) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = [
            'client_id' => $id,
            'uploaded_by' => (int) session()->get('user_id'),
            'category' => trim((string) $this->request->getPost('category')),
            'original_filename' => trim((string) $this->request->getPost('original_filename')),
            'storage_path' => trim((string) $this->request->getPost('storage_path')),
            'mime_type' => trim((string) $this->request->getPost('mime_type')) ?: null,
            'file_size' => $this->intOrNull('file_size'),
            'checksum_sha256' => trim((string) $this->request->getPost('checksum_sha256')) ?: null,
        ];

        $recordId = (int) $this->clientAttachments->insert($data);
        $this->auditLogger->record('create', 'clients', 'client_attachments', $recordId, null, $data);

        return redirect()->to('/masters/clients/' . $id)->with('success', 'Evidence record added.');
    }

    public function deleteAttachment(int $id, int $recordId)
    {
        return $this->deleteChildRecord($id, $recordId, $this->clientAttachments, 'client_attachments', 'Evidence record removed.');
    }

    private function findTenantClient(int $id): ?array
    {
        $client = $this->clients->find($id);

        if ($client === null || (int) $client['tenant_id'] !== (int) session()->get('tenant_id')) {
            return null;
        }

        return $client;
    }

    private function clientPayload(): array
    {
        return [
            'company' => trim((string) $this->request->getPost('company')),
            'legal_name' => trim((string) $this->request->getPost('legal_name')) ?: null,
            'address' => trim((string) $this->request->getPost('address')) ?: null,
            'country' => trim((string) $this->request->getPost('country')) ?: null,
            'city' => trim((string) $this->request->getPost('city')) ?: null,
            'contact_person' => trim((string) $this->request->getPost('contact_person')) ?: null,
            'designation' => trim((string) $this->request->getPost('designation')) ?: null,
            'email' => strtolower(trim((string) $this->request->getPost('email'))) ?: null,
            'phone' => trim((string) $this->request->getPost('phone')) ?: null,
            'website' => trim((string) $this->request->getPost('website')) ?: null,
            'scope' => trim((string) $this->request->getPost('scope')) ?: null,
            'employee_count' => $this->intOrNull('employee_count'),
            'permanent_employees' => $this->intOrNull('permanent_employees'),
            'temporary_employees' => $this->intOrNull('temporary_employees'),
            'shift_pattern' => trim((string) $this->request->getPost('shift_pattern')) ?: null,
            'seasonal_operations' => trim((string) $this->request->getPost('seasonal_operations')) ?: null,
            'number_of_sites' => max(1, (int) $this->request->getPost('number_of_sites')),
            'certification_status' => trim((string) $this->request->getPost('certification_status')) ?: 'enquiry',
            'risk_category' => trim((string) $this->request->getPost('risk_category')) ?: null,
            'certificate_number' => trim((string) $this->request->getPost('certificate_number')) ?: null,
            'initial_certification_date' => $this->dateOrNull('initial_certification_date'),
            'certificate_issue_date' => $this->dateOrNull('certificate_issue_date'),
            'certificate_expiry_date' => $this->dateOrNull('certificate_expiry_date'),
            'notes' => trim((string) $this->request->getPost('notes')) ?: null,
        ];
    }

    private function rules(): array
    {
        return [
            'company' => 'required|max_length[220]',
            'email' => 'permit_empty|valid_email|max_length[190]',
            'website' => 'permit_empty|valid_url_strict|max_length[220]',
            'employee_count' => 'permit_empty|integer|greater_than_equal_to[0]',
            'number_of_sites' => 'required|integer|greater_than_equal_to[1]',
        ];
    }

    private function validateLogoUpload(): bool
    {
        $file = $this->request->getFile('client_logo');

        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return true;
        }

        return $this->validate([
            'client_logo' => [
                'label' => 'Client logo',
                'rules' => 'max_size[client_logo,2048]|is_image[client_logo]|mime_in[client_logo,image/png,image/jpeg]',
            ],
        ]);
    }

    private function saveClientLogo(int $clientId): ?string
    {
        $file = $this->request->getFile('client_logo');

        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE || ! $file->isValid() || $file->hasMoved()) {
            return null;
        }

        $tenantId = (int) session()->get('tenant_id');
        $directory = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'client-logos' . DIRECTORY_SEPARATOR . 'tenant_' . $tenantId;
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $extension = strtolower($file->guessExtension() ?: $file->getExtension() ?: 'png');
        if (! in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
            $extension = 'png';
        }

        $filename = 'client_' . $clientId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)) . '.' . $extension;
        $file->move($directory, $filename, true);

        return 'uploads/client-logos/tenant_' . $tenantId . '/' . $filename;
    }

    private function clientLogoAbsolutePath(string $relativePath): string
    {
        $relativePath = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));
        if ($relativePath === '' || ! str_starts_with($relativePath, 'uploads' . DIRECTORY_SEPARATOR . 'client-logos' . DIRECTORY_SEPARATOR)) {
            return '';
        }

        $path = realpath(WRITEPATH . $relativePath);
        $base = realpath(WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'client-logos');

        if ($path === false || $base === false || ! str_starts_with($path, $base) || ! is_file($path)) {
            return '';
        }

        return $path;
    }

    private function activeStandards(): array
    {
        return (new StandardModel())->where('active', 1)->orderBy('code', 'ASC')->findAll();
    }

    private function selectedStandardIds(int $clientId): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['standard_id'],
            $this->clientStandards->where('client_id', $clientId)->findAll(),
        );
    }

    private function syncRequestedStandards(int $clientId): void
    {
        $posted = (array) $this->request->getPost('standard_ids');
        $requested = array_values(array_unique(array_filter(array_map('intval', $posted))));
        $existingRows = $this->clientStandards->where('client_id', $clientId)->findAll();
        $existing = [];

        foreach ($existingRows as $row) {
            $existing[(int) $row['standard_id']] = (int) $row['id'];
        }

        foreach ($existing as $standardId => $rowId) {
            if (! in_array($standardId, $requested, true)) {
                $this->clientStandards->delete($rowId);
            }
        }

        foreach ($requested as $standardId) {
            if (! isset($existing[$standardId])) {
                $this->clientStandards->insert([
                    'client_id' => $clientId,
                    'standard_id' => $standardId,
                    'scope' => trim((string) $this->request->getPost('scope')) ?: null,
                ]);
            }
        }
    }

    private function intOrNull(string $field): ?int
    {
        $value = $this->request->getPost($field);

        return $value === null || $value === '' ? null : (int) $value;
    }

    private function dateOrNull(string $field): ?string
    {
        $value = trim((string) $this->request->getPost($field));

        return $value === '' ? null : $value;
    }

    private function deleteChildRecord(int $clientId, int $recordId, $model, string $table, string $message)
    {
        $client = $this->findTenantClient($clientId);

        if ($client === null) {
            return redirect()->to('/masters/clients')->with('error', 'Client not found.');
        }

        $record = $model->find($recordId);

        if ($record === null || (int) $record['client_id'] !== $clientId) {
            return redirect()->to('/masters/clients/' . $clientId)->with('error', 'Record not found.');
        }

        $model->delete($recordId);
        $this->auditLogger->record('delete', 'clients', $table, $recordId, $record, null);

        return redirect()->to('/masters/clients/' . $clientId)->with('success', $message);
    }

    private function blankClient(): array
    {
        return [
            'company' => '',
            'legal_name' => '',
            'address' => '',
            'country' => '',
            'city' => '',
            'contact_person' => '',
            'designation' => '',
            'email' => '',
            'phone' => '',
            'website' => '',
            'client_logo_path' => '',
            'scope' => '',
            'employee_count' => '',
            'permanent_employees' => '',
            'temporary_employees' => '',
            'shift_pattern' => '',
            'seasonal_operations' => '',
            'number_of_sites' => 1,
            'certification_status' => 'enquiry',
            'risk_category' => '',
            'certificate_number' => '',
            'initial_certification_date' => '',
            'certificate_issue_date' => '',
            'certificate_expiry_date' => '',
            'notes' => '',
        ];
    }
}
