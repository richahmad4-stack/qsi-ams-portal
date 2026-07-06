<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

class InitialAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) env('AMS_ADMIN_EMAIL', ''));
        $password = (string) env('AMS_ADMIN_PASSWORD', '');
        $fullName = trim((string) env('AMS_ADMIN_NAME', 'QSI Administrator'));

        if ($email === '' || $password === '') {
            throw new RuntimeException('Set AMS_ADMIN_EMAIL and AMS_ADMIN_PASSWORD in .env before running InitialAdminSeeder.');
        }

        if (strlen($password) < 12) {
            throw new RuntimeException('AMS_ADMIN_PASSWORD must be at least 12 characters.');
        }

        $tenant = $this->db->query("SELECT id FROM tenants WHERE code = 'QSI' LIMIT 1")->getRowArray();

        if ($tenant === null) {
            throw new RuntimeException('Run InitialAmsSeeder before InitialAdminSeeder.');
        }

        $role = $this->db->query(
            "SELECT id FROM roles WHERE tenant_id = ? AND code = 'super_admin' LIMIT 1",
            [(int) $tenant['id']]
        )->getRowArray();

        if ($role === null) {
            throw new RuntimeException('Super User role was not found. Run InitialAmsSeeder first.');
        }

        $existing = $this->db->query(
            'SELECT id FROM users WHERE tenant_id = ? AND email = ? LIMIT 1',
            [(int) $tenant['id'], $email]
        )->getRowArray();

        if ($existing !== null) {
            return;
        }

        $this->db->transStart();

        $this->db->table('users')->insert([
            'tenant_id'             => (int) $tenant['id'],
            'primary_role_id'       => (int) $role['id'],
            'full_name'             => $fullName,
            'email'                 => $email,
            'password_hash'         => password_hash($password, PASSWORD_DEFAULT),
            'status'                => 'active',
            'must_change_password'  => 0,
            'created_at'            => date('Y-m-d H:i:s'),
        ]);

        $userId = (int) $this->db->insertID();

        $this->db->table('user_role_assignments')->insert([
            'user_id'    => $userId,
            'role_id'    => (int) $role['id'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('audit_logs')->insert([
            'tenant_id'    => (int) $tenant['id'],
            'user_id'      => $userId,
            'action'       => 'create',
            'module'       => 'authentication',
            'entity_table' => 'users',
            'entity_id'    => $userId,
            'after_json'   => json_encode(['email' => $email, 'role' => 'administrator'], JSON_THROW_ON_ERROR),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $this->db->transComplete();
    }
}
