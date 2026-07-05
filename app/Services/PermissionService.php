<?php

namespace App\Services;

use Config\Database;

class PermissionService
{
    public function currentUserCan(string $module, string $action): bool
    {
        $session = service('session');

        if (! $session->get('is_logged_in')) {
            return false;
        }

        return $this->userCan((int) $session->get('user_id'), $module, $action);
    }

    public function userCan(int $userId, string $module, string $action): bool
    {
        $db = Database::connect();

        return $db->table('permissions')
            ->join('role_permissions', 'role_permissions.permission_id = permissions.id')
            ->join('user_role_assignments', 'user_role_assignments.role_id = role_permissions.role_id')
            ->join('roles', 'roles.id = user_role_assignments.role_id')
            ->where('user_role_assignments.user_id', $userId)
            ->where('permissions.module', $module)
            ->where('permissions.action', $action)
            ->where('roles.deleted_at', null)
            ->countAllResults() > 0;
    }

    public function currentUserHasRole(string $roleCode): bool
    {
        $session = service('session');
        $roles = (array) $session->get('role_codes');

        return in_array($roleCode, $roles, true);
    }
}
