<?php

use App\Services\PermissionService;

if (! function_exists('can')) {
    function can(string $module, string $action): bool
    {
        return (new PermissionService())->currentUserCan($module, $action);
    }
}

if (! function_exists('current_user')) {
    function current_user(): ?array
    {
        if (! session()->get('is_logged_in')) {
            return null;
        }

        return [
            'id' => (int) session()->get('user_id'),
            'tenant_id' => (int) session()->get('tenant_id'),
            'tenant_code' => (string) session()->get('tenant_code'),
            'tenant_name' => (string) session()->get('tenant_name'),
            'name' => (string) session()->get('user_name'),
            'email' => (string) session()->get('user_email'),
            'roles' => (array) session()->get('role_codes'),
        ];
    }
}
