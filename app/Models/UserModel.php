<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'tenant_id',
        'primary_role_id',
        'full_name',
        'email',
        'phone',
        'password_hash',
        'status',
        'must_change_password',
        'last_login_at',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    public function findForLogin(string $tenantCode, string $email): ?array
    {
        $row = $this->select('users.*, tenants.code AS tenant_code, tenants.name AS tenant_name')
            ->join('tenants', 'tenants.id = users.tenant_id')
            ->where('tenants.code', strtoupper($tenantCode))
            ->where('users.email', strtolower($email))
            ->where('tenants.status', 'active')
            ->where('users.deleted_at', null)
            ->first();

        return $row ?: null;
    }

    public function rolesForUser(int $userId): array
    {
        return $this->db->table('roles')
            ->select('roles.id, roles.code, roles.name')
            ->join('user_role_assignments', 'user_role_assignments.role_id = roles.id')
            ->where('user_role_assignments.user_id', $userId)
            ->where('roles.deleted_at', null)
            ->orderBy('roles.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function permissionsForUser(int $userId): array
    {
        return $this->db->table('permissions')
            ->select('permissions.module, permissions.action')
            ->distinct()
            ->join('role_permissions', 'role_permissions.permission_id = permissions.id')
            ->join('user_role_assignments', 'user_role_assignments.role_id = role_permissions.role_id')
            ->join('roles', 'roles.id = user_role_assignments.role_id')
            ->where('user_role_assignments.user_id', $userId)
            ->where('roles.deleted_at', null)
            ->orderBy('permissions.module', 'ASC')
            ->orderBy('permissions.action', 'ASC')
            ->get()
            ->getResultArray();
    }
}
