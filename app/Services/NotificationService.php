<?php

namespace App\Services;

use App\Models\NotificationModel;
use Config\Database;
use Throwable;

class NotificationService
{
    public function notifyAuditorAppointment(int $appointmentId): void
    {
        $db = Database::connect();
        $appointment = $db->table('auditor_appointments')
            ->select('
                auditor_appointments.*,
                personnel.full_name,
                personnel.email,
                personnel.user_id AS personnel_user_id,
                audit_events.audit_number,
                audit_events.event_type,
                audit_events.planned_start_date,
                audit_events.planned_end_date,
                audit_programs.tenant_id,
                audit_programs.client_id,
                clients.company,
                users.email AS user_email
            ')
            ->join('personnel', 'personnel.id = auditor_appointments.personnel_id')
            ->join('users', 'users.id = personnel.user_id', 'left')
            ->join('audit_events', 'audit_events.id = auditor_appointments.audit_event_id')
            ->join('audit_programs', 'audit_programs.id = audit_events.audit_program_id')
            ->join('clients', 'clients.id = audit_programs.client_id')
            ->where('auditor_appointments.id', $appointmentId)
            ->get()
            ->getRowArray();

        if ($appointment === null || empty($appointment['personnel_user_id'])) {
            return;
        }

        $stage = ucwords(str_replace('_', ' ', (string) $appointment['event_type']));
        $title = 'Audit appointment: ' . $stage;
        $body = sprintf(
            'You have been appointed as %s for %s (%s), planned %s to %s.',
            str_replace('_', ' ', (string) $appointment['appointment_role']),
            (string) $appointment['company'],
            (string) $appointment['audit_number'],
            (string) $appointment['planned_start_date'],
            (string) $appointment['planned_end_date']
        );

        $notificationId = (int) (new NotificationModel())->insert([
            'tenant_id' => (int) $appointment['tenant_id'],
            'user_id' => (int) $appointment['personnel_user_id'],
            'title' => $title,
            'body' => $body,
            'channel' => 'dashboard',
            'related_module' => 'audit_appointment',
            'related_id' => $appointmentId,
            'status' => 'unread',
        ]);

        $email = (string) ($appointment['user_email'] ?: $appointment['email']);
        if ($email === '' || ! $this->emailNotificationsEnabled()) {
            return;
        }

        $sent = $this->sendEmail($email, (string) $appointment['full_name'], $title, $body);

        (new NotificationModel())->insert([
            'tenant_id' => (int) $appointment['tenant_id'],
            'user_id' => (int) $appointment['personnel_user_id'],
            'title' => $title,
            'body' => $body,
            'channel' => 'email',
            'related_module' => 'audit_appointment',
            'related_id' => $appointmentId,
            'status' => $sent ? 'sent' : 'failed',
            'sent_at' => $sent ? date('Y-m-d H:i:s') : null,
        ]);

        if ($notificationId > 0 && $sent) {
            (new NotificationModel())->update($notificationId, ['sent_at' => date('Y-m-d H:i:s')]);
        }
    }

    private function emailNotificationsEnabled(): bool
    {
        return filter_var((string) env('AMS_EMAIL_NOTIFICATIONS_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    private function sendEmail(string $to, string $name, string $subject, string $body): bool
    {
        try {
            $email = service('email');
            $from = (string) env('AMS_EMAIL_FROM', 'no-reply@qsi.local');
            $fromName = (string) env('AMS_EMAIL_FROM_NAME', 'QSI AMS');

            $email->setFrom($from, $fromName);
            $email->setTo($to);
            $email->setSubject($subject);
            $email->setMessage("Dear {$name},\n\n{$body}\n\nPlease log in to QSI AMS to review your assigned audit file.");

            return $email->send(false);
        } catch (Throwable) {
            return false;
        }
    }
}
