<?php

namespace App\Controllers\Operations;

use App\Controllers\BaseController;
use Config\Database;

class ReadinessController extends BaseController
{
    public function index()
    {
        $db = Database::connect();
        $tenantId = (int) session()->get('tenant_id');

        $checks = [
            $this->check(
                'Production environment',
                ENVIRONMENT === 'production',
                'Set CI_ENVIRONMENT=production on the live server.'
            ),
            $this->check(
                'Public domain and SSL',
                ! str_contains((string) env('app.baseURL', ''), 'localhost') && (str_starts_with((string) env('app.baseURL', ''), 'https://')),
                'Configure the final HTTPS domain in app.baseURL.'
            ),
            $this->check(
                'Email notifications',
                filter_var((string) env('AMS_EMAIL_NOTIFICATIONS_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN)
                    && trim((string) env('email.SMTPHost', '')) !== '',
                'Enable AMS_EMAIL_NOTIFICATIONS_ENABLED and configure SMTP settings.'
            ),
            $this->check(
                'Reminder processor',
                class_exists(\App\Commands\ProcessRemindersCommand::class),
                'Command exists. Add it to Windows Task Scheduler or hosting cron.'
            ),
            $this->check(
                'Database schema export',
                is_file(ROOTPATH . 'database/schema.sql') && is_file(ROOTPATH . 'database/seed-data.sql'),
                'Keep schema.sql and seed-data.sql updated before production handover.'
            ),
            $this->check(
                'Backup restore proof',
                is_file(ROOTPATH . 'database/restore-tested.md'),
                'Run and document one restore test before go-live.'
            ),
            $this->check(
                'Website lead table',
                $db->tableExists('website_leads'),
                'Lead table exists. Connect website/Supabase intake to this module.'
            ),
            $this->check(
                'User administration',
                $db->tableExists('user_role_assignments'),
                'Multiple-role user administration is available.'
            ),
            $this->check(
                'Password reset flow',
                $db->tableExists('password_reset_tokens') && class_exists(\App\Controllers\Auth\PasswordResetController::class),
                'Password reset tokens and controller must be available before go-live.'
            ),
            $this->check(
                'Automated test workflow',
                is_file(ROOTPATH . '.github/workflows/phpunit.yml'),
                'Add GitHub Actions so tests run on every push.'
            ),
        ];

        $leadCount = $db->tableExists('website_leads')
            ? (int) $db->table('website_leads')->where('tenant_id', $tenantId)->where('status', 'new')->countAllResults()
            : 0;

        $openReminders = $db->tableExists('audit_reminders')
            ? (int) $db->table('audit_reminders')->where('status', 'open')->groupStart()->where('tenant_id', $tenantId)->orWhere('tenant_id', null)->groupEnd()->countAllResults()
            : 0;

        return view('operations/readiness', [
            'title' => 'Operations Readiness',
            'pageTitle' => 'Operations Readiness',
            'pageSubtitle' => 'Production, reminders, email, leads, users and backup readiness',
            'checks' => $checks,
            'summary' => [
                'ready' => count(array_filter($checks, static fn (array $check): bool => $check['ready'])),
                'total' => count($checks),
                'new_leads' => $leadCount,
                'open_reminders' => $openReminders,
                'environment' => ENVIRONMENT,
                'base_url' => (string) env('app.baseURL', ''),
                'email_enabled' => filter_var((string) env('AMS_EMAIL_NOTIFICATIONS_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
            ],
        ]);
    }

    private function check(string $label, bool $ready, string $nextAction): array
    {
        return [
            'label' => $label,
            'ready' => $ready,
            'next_action' => $nextAction,
        ];
    }
}
