<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\RoleModel;
use App\Models\UserModel;
use App\Services\AuditLogger;
use App\Services\PasswordPolicy;
use Config\Database;

class UserController extends BaseController
{
    private UserModel $users;
    private RoleModel $roles;
    private AuditLogger $auditLogger;

    public function __construct()
    {
        $this->users = new UserModel();
        $this->roles = new RoleModel();
        $this->auditLogger = new AuditLogger();
    }

    public function index()
    {
        $tenantId = (int) session()->get('tenant_id');
        $rows = $this->users
            ->select('users.id, users.full_name, users.email, users.phone, users.status, users.must_change_password, users.last_login_at, users.created_at')
            ->where('users.tenant_id', $tenantId)
            ->orderBy('users.full_name', 'ASC')
            ->findAll();

        foreach ($rows as &$row) {
            $row['roles'] = $this->users->rolesForUser((int) $row['id']);
        }
        unset($row);

        return view('admin/users/index', [
            'title' => 'Users',
            'pageTitle' => 'User Administration',
            'pageSubtitle' => 'Create users, assign roles and control access',
            'users' => $rows,
        ]);
    }

    public function new()
    {
        return view('admin/users/form', [
            'title' => 'New User',
            'pageTitle' => 'New User',
            'pageSubtitle' => 'Create a login and assign roles',
            'user' => $this->blankUser(),
            'roles' => $this->availableRoles(),
            'assignedRoleIds' => [],
            'action' => site_url('admin/users'),
            'isNew' => true,
        ]);
    }

    public function create()
    {
        if (! $this->validate($this->rules(true))) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $tenantId = (int) session()->get('tenant_id');
        $email = strtolower(trim((string) $this->request->getPost('email')));
        if ($this->emailExists($email)) {
            return redirect()->back()->withInput()->with('error', 'A user with this email already exists.');
        }

        $roleIds = $this->postedRoleIds();
        if ($roleIds === []) {
            return redirect()->back()->withInput()->with('error', 'Select at least one role.');
        }

        $policy = new PasswordPolicy();
        $password = trim((string) $this->request->getPost('password'));
        if ($password === '') {
            $password = $policy->temporaryPassword();
        } elseif (! $policy->isStrong($password)) {
            return redirect()->back()->withInput()->with('error', PasswordPolicy::MESSAGE);
        }

        $data = [
            'tenant_id' => $tenantId,
            'primary_role_id' => $roleIds[0],
            'full_name' => trim((string) $this->request->getPost('full_name')),
            'email' => $email,
            'phone' => trim((string) $this->request->getPost('phone')) ?: null,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'status' => (string) $this->request->getPost('status'),
            'must_change_password' => 1,
        ];

        $id = (int) $this->users->insert($data);
        $this->syncRoles($id, $roleIds);
        $this->auditLogger->record('create', 'users', 'users', $id, null, $this->safeUserLog($data, $roleIds));

        return redirect()->to('/admin/users')->with('success', 'User created. Temporary password: ' . $password);
    }

    public function edit(int $id)
    {
        $user = $this->tenantUser($id);
        if ($user === null) {
            return redirect()->to('/admin/users')->with('error', 'User not found.');
        }

        return view('admin/users/form', [
            'title' => 'Edit User',
            'pageTitle' => 'Edit User',
            'pageSubtitle' => $user['full_name'],
            'user' => $user,
            'roles' => $this->availableRoles(),
            'assignedRoleIds' => array_map(static fn (array $role): int => (int) $role['id'], $this->users->rolesForUser($id)),
            'action' => site_url('admin/users/' . $id),
            'isNew' => false,
        ]);
    }

    public function update(int $id)
    {
        $user = $this->tenantUser($id);
        if ($user === null) {
            return redirect()->to('/admin/users')->with('error', 'User not found.');
        }

        if (! $this->validate($this->rules(false, $id))) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $email = strtolower(trim((string) $this->request->getPost('email')));
        if ($this->emailExists($email, $id)) {
            return redirect()->back()->withInput()->with('error', 'A user with this email already exists.');
        }

        $roleIds = $this->postedRoleIds();
        if ($roleIds === []) {
            return redirect()->back()->withInput()->with('error', 'Select at least one role.');
        }

        $data = [
            'primary_role_id' => $roleIds[0],
            'full_name' => trim((string) $this->request->getPost('full_name')),
            'email' => $email,
            'phone' => trim((string) $this->request->getPost('phone')) ?: null,
            'status' => (string) $this->request->getPost('status'),
            'must_change_password' => $this->request->getPost('must_change_password') === '1' ? 1 : 0,
        ];

        $newPassword = trim((string) $this->request->getPost('password'));
        if ($newPassword !== '') {
            $policy = new PasswordPolicy();
            if (! $policy->isStrong($newPassword)) {
                return redirect()->back()->withInput()->with('error', PasswordPolicy::MESSAGE);
            }

            $data['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            $data['must_change_password'] = 1;
        }

        $this->users->update($id, $data);
        $this->syncRoles($id, $roleIds);
        $this->auditLogger->record('update', 'users', 'users', $id, $this->safeUserLog($user), $this->safeUserLog($data, $roleIds));

        return redirect()->to('/admin/users')->with('success', 'User updated.');
    }

    public function deactivate(int $id)
    {
        $user = $this->tenantUser($id);
        if ($user === null) {
            return redirect()->to('/admin/users')->with('error', 'User not found.');
        }

        if ($id === (int) session()->get('user_id')) {
            return redirect()->to('/admin/users')->with('error', 'You cannot deactivate your own login.');
        }

        $this->users->update($id, ['status' => 'inactive']);
        $this->auditLogger->record('delete', 'users', 'users', $id, $this->safeUserLog($user), ['status' => 'inactive']);

        return redirect()->to('/admin/users')->with('success', 'User deactivated.');
    }

    private function rules(bool $new, ?int $id = null): array
    {
        return [
            'full_name' => 'required|max_length[180]',
            'email' => 'required|valid_email|max_length[190]',
            'phone' => 'permit_empty|max_length[40]',
            'status' => 'required|in_list[active,inactive,suspended]',
            'password' => 'permit_empty',
        ];
    }

    private function blankUser(): array
    {
        return [
            'id' => null,
            'full_name' => '',
            'email' => '',
            'phone' => '',
            'status' => 'active',
            'must_change_password' => 1,
        ];
    }

    private function availableRoles(): array
    {
        return $this->roles
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    private function postedRoleIds(): array
    {
        $posted = (array) $this->request->getPost('roles');
        $allowed = array_column($this->availableRoles(), 'id');
        $roleIds = [];

        foreach ($posted as $roleId) {
            $roleId = (int) $roleId;
            if (in_array($roleId, array_map('intval', $allowed), true)) {
                $roleIds[] = $roleId;
            }
        }

        return array_values(array_unique($roleIds));
    }

    private function syncRoles(int $userId, array $roleIds): void
    {
        $db = Database::connect();
        $db->table('user_role_assignments')->where('user_id', $userId)->delete();

        foreach ($roleIds as $roleId) {
            $db->table('user_role_assignments')->insert([
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        }
    }

    private function tenantUser(int $id): ?array
    {
        $user = $this->users
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->find($id);

        return $user ?: null;
    }

    private function emailExists(string $email, ?int $ignoreUserId = null): bool
    {
        $builder = $this->users
            ->where('tenant_id', (int) session()->get('tenant_id'))
            ->where('email', $email);

        if ($ignoreUserId !== null) {
            $builder->where('id !=', $ignoreUserId);
        }

        return $builder->countAllResults() > 0;
    }

    private function safeUserLog(array $data, array $roleIds = []): array
    {
        unset($data['password_hash']);
        if ($roleIds !== []) {
            $data['role_ids'] = $roleIds;
        }

        return $data;
    }
}
