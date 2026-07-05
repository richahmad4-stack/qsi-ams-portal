<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Services\AuthService;
use App\Services\DashboardService;
use Config\Database;

class DashboardController extends BaseController
{
    public function index()
    {
        $user = (new AuthService())->currentUser();

        return view('dashboard/index', [
            'title' => 'Dashboard',
            'user' => $user,
            'dashboard' => (new DashboardService())->metrics((int) $user['tenant_id']),
            'notifications' => Database::connect()->table('notifications')
                ->where('tenant_id', (int) $user['tenant_id'])
                ->where('user_id', (int) $user['id'])
                ->orderBy('created_at', 'DESC')
                ->get(8)
                ->getResultArray(),
        ]);
    }
}
