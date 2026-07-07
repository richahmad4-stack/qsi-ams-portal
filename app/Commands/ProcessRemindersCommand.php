<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class ProcessRemindersCommand extends BaseCommand
{
    protected $group = 'AMS';
    protected $name = 'ams:process-reminders';
    protected $description = 'Creates due surveillance, audit and certificate reminders for AMS.';

    public function run(array $params)
    {
        $db = Database::connect();
        $today = date('Y-m-d');
        $auditLimit = date('Y-m-d', strtotime('+30 days'));
        $certificateLimit = date('Y-m-d', strtotime('+90 days'));
        $created = 0;

        if (! $db->tableExists('audit_reminders')) {
            CLI::error('audit_reminders table does not exist.');
            return EXIT_ERROR;
        }

        $events = $db->table('audit_events')
            ->select('
                audit_events.id AS audit_event_id,
                audit_events.audit_number,
                audit_events.event_type,
                audit_events.planned_start_date,
                audit_events.status,
                audit_programs.tenant_id,
                audit_programs.client_id,
                clients.company
            ')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->join('clients', 'clients.id = audit_programs.client_id')
            ->where('audit_events.planned_start_date IS NOT NULL', null, false)
            ->whereNotIn('audit_events.status', ['completed', 'cancelled'])
            ->where('audit_events.planned_start_date <=', $auditLimit)
            ->get()
            ->getResultArray();

        foreach ($events as $event) {
            $dueDate = (string) $event['planned_start_date'];
            $overdue = $dueDate < $today;
            $type = $overdue ? 'audit_overdue' : 'audit_upcoming';
            $stage = ucwords(str_replace('_', ' ', (string) $event['event_type']));
            $title = ($overdue ? 'Overdue audit: ' : 'Upcoming audit: ') . $stage;
            $message = sprintf(
                '%s for %s (%s) is %s %s.',
                $stage,
                (string) $event['company'],
                (string) $event['audit_number'],
                $overdue ? 'overdue since' : 'due on',
                $dueDate
            );

            if ($this->createReminder($db, [
                'tenant_id' => (int) $event['tenant_id'],
                'client_id' => (int) $event['client_id'],
                'audit_event_id' => (int) $event['audit_event_id'],
                'reminder_type' => $type,
                'title' => $title,
                'message' => $message,
                'due_date' => $dueDate,
                'status' => 'open',
            ])) {
                $created++;
                $this->notifyAdmins($db, (int) $event['tenant_id'], $title, $message, 'audit_reminder', (int) $event['audit_event_id']);
            }
        }

        $clients = $db->table('clients')
            ->select('id, tenant_id, company, certificate_number, certificate_expiry_date')
            ->where('certificate_expiry_date IS NOT NULL', null, false)
            ->where('certificate_expiry_date <=', $certificateLimit)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        foreach ($clients as $client) {
            $dueDate = (string) $client['certificate_expiry_date'];
            $overdue = $dueDate < $today;
            $type = $overdue ? 'certificate_expiry_overdue' : 'certificate_expiry_upcoming';
            $title = ($overdue ? 'Expired certificate: ' : 'Certificate expiry due: ') . (string) $client['company'];
            $message = sprintf(
                'Certificate %s for %s is %s %s.',
                (string) ($client['certificate_number'] ?: 'not numbered'),
                (string) $client['company'],
                $overdue ? 'expired since' : 'expiring on',
                $dueDate
            );

            if ($this->createReminder($db, [
                'tenant_id' => (int) $client['tenant_id'],
                'client_id' => (int) $client['id'],
                'audit_event_id' => null,
                'reminder_type' => $type,
                'title' => $title,
                'message' => $message,
                'due_date' => $dueDate,
                'status' => 'open',
            ])) {
                $created++;
                $this->notifyAdmins($db, (int) $client['tenant_id'], $title, $message, 'certificate_reminder', (int) $client['id']);
            }
        }

        CLI::write('Reminder processing complete. New reminders: ' . $created, 'green');

        return EXIT_SUCCESS;
    }

    private function createReminder($db, array $data): bool
    {
        $builder = $db->table('audit_reminders')
            ->where('reminder_type', $data['reminder_type'])
            ->where('due_date', $data['due_date']);

        if ($data['audit_event_id'] !== null) {
            $builder->where('audit_event_id', $data['audit_event_id']);
        } else {
            $builder->where('audit_event_id', null)->where('client_id', $data['client_id']);
        }

        if ($builder->countAllResults() > 0) {
            return false;
        }

        $db->table('audit_reminders')->insert($data + ['created_at' => date('Y-m-d H:i:s')]);

        return true;
    }

    private function notifyAdmins($db, int $tenantId, string $title, string $message, string $module, int $relatedId): void
    {
        $users = $db->table('users')
            ->select('users.id')
            ->join('user_role_assignments', 'user_role_assignments.user_id = users.id')
            ->join('roles', 'roles.id = user_role_assignments.role_id')
            ->where('users.tenant_id', $tenantId)
            ->where('users.status', 'active')
            ->whereIn('roles.code', ['super_admin', 'administrator', 'certification_manager'])
            ->groupBy('users.id')
            ->get()
            ->getResultArray();

        foreach ($users as $user) {
            $exists = $db->table('notifications')
                ->where('tenant_id', $tenantId)
                ->where('user_id', (int) $user['id'])
                ->where('related_module', $module)
                ->where('related_id', $relatedId)
                ->where('title', $title)
                ->countAllResults();

            if ($exists > 0) {
                continue;
            }

            $db->table('notifications')->insert([
                'tenant_id' => $tenantId,
                'user_id' => (int) $user['id'],
                'title' => $title,
                'body' => $message,
                'channel' => 'dashboard',
                'related_module' => $module,
                'related_id' => $relatedId,
                'status' => 'unread',
            ]);
        }
    }
}
