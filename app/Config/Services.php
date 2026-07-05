<?php

namespace Config;

use App\Services\AuthService;
use App\Services\PermissionService;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function auth(bool $getShared = true): AuthService
    {
        if ($getShared) {
            return static::getSharedInstance('auth');
        }

        return new AuthService();
    }

    public static function permissions(bool $getShared = true): PermissionService
    {
        if ($getShared) {
            return static::getSharedInstance('permissions');
        }

        return new PermissionService();
    }
}
