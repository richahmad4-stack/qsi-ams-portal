<?php

namespace App\Services;

use App\Models\LoginAttemptModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

class AuthService
{
    private const MAX_FAILURES = 5;
    private const THROTTLE_MINUTES = 15;

    private BaseConnection $db;
    private UserModel $users;
    private LoginAttemptModel $loginAttempts;
    private AuditLogger $auditLogger;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->users = new UserModel();
        $this->loginAttempts = new LoginAttemptModel();
        $this->auditLogger = new AuditLogger();
    }

    public function attempt(string $tenantCode, string $email, string $password, string $ipAddress, string $userAgent): array
    {
        $tenantCode = strtoupper(trim($tenantCode));
        $email = strtolower(trim($email));
        $userAgent = substr($userAgent, 0, 500);

        if ($this->loginAttempts->recentFailures($tenantCode, $email, $ipAddress, self::THROTTLE_MINUTES) >= self::MAX_FAILURES) {
            $this->recordAttempt(null, $email, $ipAddress, $userAgent, false, 'too_many_attempts');

            return [
                'success' => false,
                'message' => 'Too many failed attempts. Please wait 15 minutes and try again.',
            ];
        }

        $user = $this->users->findForLogin($tenantCode, $email);

        if ($user === null) {
            $this->recordAttempt($this->tenantIdForCode($tenantCode), $email, $ipAddress, $userAgent, false, 'user_not_found');

            return [
                'success' => false,
                'message' => 'The login details are not valid.',
            ];
        }

        if ($user['status'] !== 'active') {
            $this->recordAttempt((int) $user['tenant_id'], $email, $ipAddress, $userAgent, false, 'inactive_user');

            return [
                'success' => false,
                'message' => 'This user account is not active.',
            ];
        }

        if (! password_verify($password, $user['password_hash'])) {
            $this->recordAttempt((int) $user['tenant_id'], $email, $ipAddress, $userAgent, false, 'invalid_password');

            return [
                'success' => false,
                'message' => 'The login details are not valid.',
            ];
        }

        $this->recordAttempt((int) $user['tenant_id'], $email, $ipAddress, $userAgent, true, null);
        $this->startSession($user);

        $this->users->update((int) $user['id'], [
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        $this->auditLogger->record(
            'login',
            'authentication',
            'users',
            (int) $user['id'],
            null,
            ['email' => $email],
            (int) $user['tenant_id'],
            (int) $user['id']
        );

        return [
            'success' => true,
            'message' => 'Login successful.',
            'must_change_password' => (bool) $user['must_change_password'],
        ];
    }

    public function logout(): void
    {
        $session = service('session');

        if ($session->get('is_logged_in')) {
            $this->auditLogger->record('logout', 'authentication', 'users', (int) $session->get('user_id'));
        }

        $session->destroy();
    }

    public function currentUser(): ?array
    {
        $session = service('session');

        if (! $session->get('is_logged_in')) {
            return null;
        }

        return [
            'id' => (int) $session->get('user_id'),
            'tenant_id' => (int) $session->get('tenant_id'),
            'tenant_code' => (string) $session->get('tenant_code'),
            'tenant_name' => (string) $session->get('tenant_name'),
            'name' => (string) $session->get('user_name'),
            'email' => (string) $session->get('user_email'),
            'roles' => (array) $session->get('role_codes'),
        ];
    }

    private function startSession(array $user): void
    {
        $session = service('session');
        $roles = $this->users->rolesForUser((int) $user['id']);

        $session->regenerate(true);
        $session->set([
            'is_logged_in' => true,
            'user_id' => (int) $user['id'],
            'tenant_id' => (int) $user['tenant_id'],
            'tenant_code' => (string) $user['tenant_code'],
            'tenant_name' => (string) $user['tenant_name'],
            'user_name' => (string) $user['full_name'],
            'user_email' => (string) $user['email'],
            'role_codes' => array_column($roles, 'code'),
            'login_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function recordAttempt(
        ?int $tenantId,
        string $email,
        string $ipAddress,
        string $userAgent,
        bool $successful,
        ?string $failureReason
    ): void {
        $this->loginAttempts->insert([
            'tenant_id' => $tenantId,
            'email' => $email,
            'ip_address' => $ipAddress,
            'successful' => $successful ? 1 : 0,
            'failure_reason' => $failureReason,
            'user_agent' => $userAgent,
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function tenantIdForCode(string $tenantCode): ?int
    {
        $tenant = $this->db->table('tenants')
            ->select('id')
            ->where('code', $tenantCode)
            ->get()
            ->getRowArray();

        return $tenant === null ? null : (int) $tenant['id'];
    }
}
