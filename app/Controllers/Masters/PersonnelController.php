<?php

namespace App\Controllers\Masters;

use App\Controllers\BaseController;
use App\Models\ClientModel;
use App\Models\FoodChainCategoryModel;
use App\Models\IafCodeModel;
use App\Models\MedicalDeviceCategoryModel;
use App\Models\PersonnelCompetencyModel;
use App\Models\PersonnelModel;
use App\Models\StandardModel;
use App\Models\UserModel;
use App\Services\AuditLogger;
use Config\Database;

class PersonnelController extends BaseController
{
    private PersonnelModel $personnel;
    private PersonnelCompetencyModel $competencies;
    private UserModel $users;
    private AuditLogger $auditLogger;

    public function __construct()
    {
        $this->personnel = new PersonnelModel();
        $this->competencies = new PersonnelCompetencyModel();
        $this->users = new UserModel();
        $this->auditLogger = new AuditLogger();
    }

    public function index()
    {
        $affiliation = (string) $this->request->getGet('affiliation');
        $query = $this->personnel
            ->select('personnel.*, clients.company AS linked_client_company')
            ->join('clients', 'clients.id = personnel.client_id', 'left')
            ->where('personnel.tenant_id', (int) session()->get('tenant_id'));

        if ($affiliation === 'client') {
            $query->where('personnel.personnel_type', 'client_representative');
        } elseif ($affiliation === 'certification_body') {
            $query->where('personnel.personnel_type !=', 'client_representative');
        }

        return view('masters/personnel/index', [
            'title' => 'Personnel',
            'pageTitle' => 'Personnel Master',
            'pageSubtitle' => 'Certification body personnel and client representatives',
            'affiliation' => $affiliation,
            'personnel' => $query
                ->orderBy('personnel.full_name', 'ASC')
                ->findAll(),
        ]);
    }

    public function new()
    {
        return view('masters/personnel/form', [
            'title' => 'New Personnel',
            'pageTitle' => 'New Personnel',
            'pageSubtitle' => 'Create a personnel profile',
            'person' => $this->blank(),
            'clients' => $this->clientsForForm(),
            'action' => site_url('masters/personnel'),
        ]);
    }

    public function create()
    {
        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = $this->payload();
        $data['tenant_id'] = (int) session()->get('tenant_id');

        $id = (int) $this->personnel->insert($data);
        $this->auditLogger->record('create', 'personnel', 'personnel', $id, null, $data);

        return redirect()->to('/masters/personnel')->with('success', 'Personnel profile created.');
    }

    public function edit(int $id)
    {
        $person = $this->findTenantPerson($id);

        if ($person === null) {
            return redirect()->to('/masters/personnel')->with('error', 'Personnel profile not found.');
        }

        return view('masters/personnel/form', [
            'title' => 'Edit Personnel',
            'pageTitle' => 'Edit Personnel',
            'pageSubtitle' => $person['full_name'],
            'person' => $person,
            'clients' => $this->clientsForForm(),
            'action' => site_url('masters/personnel/' . $id),
        ]);
    }

    public function show(int $id)
    {
        $person = $this->findTenantPerson($id);

        if ($person === null) {
            return redirect()->to('/masters/personnel')->with('error', 'Personnel profile not found.');
        }

        return view('masters/personnel/show', [
            'title' => 'Personnel Details',
            'pageTitle' => $person['full_name'],
            'pageSubtitle' => 'Competency matrix and approval records',
            'person' => $person,
            'competencies' => $this->competencies
                ->select('personnel_competencies.*, standards.code AS standard_code, iaf_codes.code AS iaf_code, food_chain_categories.code AS food_code, medical_device_categories.code AS medical_code')
                ->join('standards', 'standards.id = personnel_competencies.standard_id', 'left')
                ->join('iaf_codes', 'iaf_codes.id = personnel_competencies.iaf_code_id', 'left')
                ->join('food_chain_categories', 'food_chain_categories.id = personnel_competencies.food_chain_category_id', 'left')
                ->join('medical_device_categories', 'medical_device_categories.id = personnel_competencies.medical_device_category_id', 'left')
                ->where('personnel_competencies.personnel_id', $id)
                ->orderBy('standards.code', 'ASC')
                ->findAll(),
            'standards' => (new StandardModel())->where('active', 1)->orderBy('code', 'ASC')->findAll(),
            'iafCodes' => (new IafCodeModel())->where('active', 1)->orderBy('code', 'ASC')->findAll(),
            'foodCategories' => (new FoodChainCategoryModel())->where('active', 1)->orderBy('code', 'ASC')->findAll(),
            'medicalCategories' => (new MedicalDeviceCategoryModel())->where('active', 1)->orderBy('code', 'ASC')->findAll(),
        ]);
    }

    public function update(int $id)
    {
        $person = $this->findTenantPerson($id);

        if ($person === null) {
            return redirect()->to('/masters/personnel')->with('error', 'Personnel profile not found.');
        }

        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = $this->payload();
        if (($loginError = $this->linkedLoginValidationError($person, $data)) !== null) {
            return redirect()->back()->withInput()->with('error', $loginError);
        }

        $this->personnel->update($id, $data);
        $this->updateLinkedLogin($person, $data);
        $this->auditLogger->record('update', 'personnel', 'personnel', $id, $person, $data);

        return redirect()->to('/masters/personnel')->with('success', 'Personnel profile updated.');
    }

    public function delete(int $id)
    {
        $person = $this->findTenantPerson($id);

        if ($person === null) {
            return redirect()->to('/masters/personnel')->with('error', 'Personnel profile not found.');
        }

        $this->personnel->delete($id);
        $this->auditLogger->record('delete', 'personnel', 'personnel', $id, $person, null);

        return redirect()->to('/masters/personnel')->with('success', 'Personnel profile deleted.');
    }

    public function addCompetency(int $id)
    {
        $person = $this->findTenantPerson($id);

        if ($person === null) {
            return redirect()->to('/masters/personnel')->with('error', 'Personnel profile not found.');
        }

        if (! $this->validate([
            'competency_type' => 'required|max_length[80]',
            'approval_status' => 'required|max_length[40]',
        ])) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = [
            'personnel_id' => $id,
            'standard_id' => $this->intOrNull('standard_id'),
            'iaf_code_id' => $this->intOrNull('iaf_code_id'),
            'food_chain_category_id' => $this->intOrNull('food_chain_category_id'),
            'medical_device_category_id' => $this->intOrNull('medical_device_category_id'),
            'competency_type' => trim((string) $this->request->getPost('competency_type')),
            'valid_from' => $this->dateOrNull('valid_from'),
            'valid_until' => $this->dateOrNull('valid_until'),
            'approval_status' => trim((string) $this->request->getPost('approval_status')),
            'evidence_notes' => trim((string) $this->request->getPost('evidence_notes')) ?: null,
        ];

        $recordId = (int) $this->competencies->insert($data);
        $this->auditLogger->record('create', 'competency_matrix', 'personnel_competencies', $recordId, null, $data);

        return redirect()->to('/masters/personnel/' . $id)->with('success', 'Competency added.');
    }

    public function deleteCompetency(int $id, int $recordId)
    {
        $person = $this->findTenantPerson($id);

        if ($person === null) {
            return redirect()->to('/masters/personnel')->with('error', 'Personnel profile not found.');
        }

        $record = $this->competencies->find($recordId);

        if ($record === null || (int) $record['personnel_id'] !== $id) {
            return redirect()->to('/masters/personnel/' . $id)->with('error', 'Competency not found.');
        }

        $this->competencies->delete($recordId);
        $this->auditLogger->record('delete', 'competency_matrix', 'personnel_competencies', $recordId, $record, null);

        return redirect()->to('/masters/personnel/' . $id)->with('success', 'Competency removed.');
    }

    private function findTenantPerson(int $id): ?array
    {
        $person = $this->personnel
            ->select('personnel.*, clients.company AS linked_client_company, users.email AS login_email, users.status AS login_status, users.must_change_password AS login_must_change_password')
            ->join('clients', 'clients.id = personnel.client_id', 'left')
            ->join('users', 'users.id = personnel.user_id', 'left')
            ->where('personnel.id', $id)
            ->where('personnel.tenant_id', (int) session()->get('tenant_id'))
            ->first();

        if ($person === null) {
            return null;
        }

        return $person;
    }

    private function rules(): array
    {
        return [
            'full_name' => 'required|max_length[180]',
            'email' => 'permit_empty|valid_email|max_length[190]',
            'personnel_type' => 'required|max_length[80]',
            'client_id' => 'permit_empty|integer',
            'approval_status' => 'required|max_length[40]',
            'new_password' => 'permit_empty|min_length[8]|max_length[255]',
            'confirm_password' => 'permit_empty|max_length[255]',
        ];
    }

    private function linkedLoginValidationError(array $person, array $data): ?string
    {
        if (! $this->shouldManageLinkedLogin($person, $data) && ! $this->shouldManageClientLogin($data)) {
            return null;
        }

        if (($data['email'] ?? null) === null) {
            return ($data['personnel_type'] ?? '') === 'client_representative'
                ? 'Email is required for client portal login.'
                : 'Email is required for staff login.';
        }

        if (($data['personnel_type'] ?? '') === 'client_representative' && ($data['client_id'] ?? null) === null) {
            return 'Link the client representative to a client before enabling client portal login.';
        }

        $password = trim((string) $this->request->getPost('new_password'));
        $confirm = trim((string) $this->request->getPost('confirm_password'));
        if ($password !== '' && $password !== $confirm) {
            return 'The password confirmation does not match.';
        }

        if (($data['personnel_type'] ?? '') === 'client_representative' && (int) ($person['user_id'] ?? 0) <= 0 && $password === '') {
            return 'Enter a password when enabling a new client portal login.';
        }

        $duplicateQuery = $this->users
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->where('email', (string) $data['email']);

        if ((int) ($person['user_id'] ?? 0) > 0) {
            $duplicateQuery->where('id !=', (int) $person['user_id']);
        }

        $duplicate = $duplicateQuery->first();

        return $duplicate === null ? null : 'A login user with this email already exists.';
    }

    private function updateLinkedLogin(array $person, array $data): void
    {
        if (! $this->shouldManageLinkedLogin($person, $data)) {
            $this->syncClientPortalLogin($person, $data);
            return;
        }

        $payload = [
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'status' => ($data['approval_status'] ?? '') === 'suspended' ? 'inactive' : 'active',
            'must_change_password' => $this->request->getPost('must_change_password') === '1' ? 1 : 0,
        ];

        $password = trim((string) $this->request->getPost('new_password'));
        if ($password !== '') {
            $payload['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $this->users->update((int) $person['user_id'], $payload);
    }

    private function shouldManageLinkedLogin(array $person, array $data): bool
    {
        return (int) ($person['user_id'] ?? 0) > 0
            && ($data['personnel_type'] ?? '') !== 'client_representative';
    }

    private function shouldManageClientLogin(array $data): bool
    {
        return ($data['personnel_type'] ?? '') === 'client_representative'
            && $this->request->getPost('enable_client_login') === '1';
    }

    private function syncClientPortalLogin(array $person, array $data): void
    {
        if (($data['personnel_type'] ?? '') !== 'client_representative') {
            return;
        }

        $userId = (int) ($person['user_id'] ?? 0);
        if (! $this->shouldManageClientLogin($data)) {
            if ($userId > 0) {
                $this->users->update($userId, ['status' => 'inactive']);
            }

            return;
        }

        $roleId = $this->clientRepresentativeRoleId((int) session()->get('tenant_id'));
        if ($roleId === null) {
            throw new \RuntimeException('Client Representative role is not configured.');
        }

        $payload = [
            'tenant_id' => (int) session()->get('tenant_id'),
            'primary_role_id' => $roleId,
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'status' => 'active',
            'must_change_password' => $this->request->getPost('must_change_password') === '1' ? 1 : 0,
        ];

        $password = trim((string) $this->request->getPost('new_password'));
        if ($password !== '') {
            $payload['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        if ($userId > 0) {
            $this->users->update($userId, $payload);
        } else {
            $payload['password_hash'] ??= password_hash('Password123!', PASSWORD_DEFAULT);
            $userId = (int) $this->users->insert($payload);
            $this->personnel->update((int) $person['id'], ['user_id' => $userId]);
        }

        $this->syncSingleRole($userId, $roleId);
    }

    private function clientRepresentativeRoleId(int $tenantId): ?int
    {
        $role = Database::connect()->table('roles')
            ->select('id')
            ->where('tenant_id', $tenantId)
            ->where('code', 'client_representative')
            ->get(1)
            ->getRowArray();

        return $role === null ? null : (int) $role['id'];
    }

    private function syncSingleRole(int $userId, int $roleId): void
    {
        $db = Database::connect();
        $db->table('user_role_assignments')->where('user_id', $userId)->delete();
        $db->table('user_role_assignments')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
    }

    private function payload(): array
    {
        $type = trim((string) $this->request->getPost('personnel_type'));

        return [
            'full_name' => trim((string) $this->request->getPost('full_name')),
            'email' => strtolower(trim((string) $this->request->getPost('email'))) ?: null,
            'phone' => trim((string) $this->request->getPost('phone')) ?: null,
            'personnel_type' => $type,
            'client_id' => $type === 'client_representative' ? $this->intOrNull('client_id') : null,
            'approval_status' => trim((string) $this->request->getPost('approval_status')),
            'languages' => $this->jsonList('languages'),
            'countries' => $this->jsonList('countries'),
            'experience_summary' => trim((string) $this->request->getPost('experience_summary')) ?: null,
        ];
    }

    private function jsonList(string $field): ?string
    {
        $value = trim((string) $this->request->getPost($field));

        if ($value === '') {
            return null;
        }

        $items = array_values(array_filter(array_map('trim', explode(',', $value))));

        return json_encode($items, JSON_THROW_ON_ERROR);
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

    private function clientsForForm(): array
    {
        return (new ClientModel())
            ->select('id, company')
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->orderBy('company', 'ASC')
            ->findAll();
    }

    private function blank(): array
    {
        return [
            'full_name' => '',
            'email' => '',
            'phone' => '',
            'personnel_type' => 'auditor',
            'client_id' => '',
            'approval_status' => 'pending',
            'languages' => '',
            'countries' => '',
            'experience_summary' => '',
        ];
    }
}
