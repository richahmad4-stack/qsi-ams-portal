<?php

namespace App\Models;

use CodeIgniter\Model;

class LoginAttemptModel extends Model
{
    protected $table = 'login_attempts';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tenant_id',
        'email',
        'ip_address',
        'successful',
        'failure_reason',
        'user_agent',
        'attempted_at',
    ];
    protected $useTimestamps = false;

    public function recentFailures(string $tenantCode, string $email, string $ipAddress, int $minutes): int
    {
        $since = date('Y-m-d H:i:s', time() - ($minutes * 60));

        return (int) $this->select('login_attempts.id')
            ->join('tenants', 'tenants.id = login_attempts.tenant_id', 'left')
            ->where('login_attempts.email', strtolower($email))
            ->where('login_attempts.ip_address', $ipAddress)
            ->where('login_attempts.successful', 0)
            ->where('login_attempts.attempted_at >=', $since)
            ->groupStart()
                ->where('tenants.code', strtoupper($tenantCode))
                ->orWhere('login_attempts.tenant_id', null)
            ->groupEnd()
            ->countAllResults();
    }
}
