<?php

namespace App\Database\Migrations;

use App\Services\CertificationApplicationDefaults;
use CodeIgniter\Database\Migration;

class AddHaccpPlansApplicationQuestion extends Migration
{
    private CertificationApplicationDefaults $defaults;

    public function up(): void
    {
        if (! $this->db->tableExists('question_library')) {
            return;
        }

        $this->defaults = new CertificationApplicationDefaults();
        $this->db->transStart();
        $this->upsertQuestion();
        $this->backfillApplications();
        $this->backfillApplicationReviews();
        $this->db->transComplete();
    }

    public function down(): void
    {
        if ($this->db->tableExists('question_library')) {
            $this->db->table('question_library')
                ->where('question_key', 'haccp_plans_processes')
                ->update(['active' => 0]);
        }
    }

    private function upsertQuestion(): void
    {
        $this->db->query(
            'INSERT INTO question_library
                (question_key, question_text, question_type, applicable_standards, mandatory, section, display_order, validation_rules, help_text, default_answer, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, 1)
             ON DUPLICATE KEY UPDATE
                question_text = VALUES(question_text),
                question_type = VALUES(question_type),
                applicable_standards = VALUES(applicable_standards),
                mandatory = VALUES(mandatory),
                section = VALUES(section),
                display_order = VALUES(display_order),
                validation_rules = VALUES(validation_rules),
                active = 1',
            [
                'haccp_plans_processes',
                'Number of HACCP Studies / Plans',
                'number',
                json_encode(['HACCP'], JSON_THROW_ON_ERROR),
                0,
                'Certification Required',
                505,
                null,
            ]
        );
    }

    private function backfillApplications(): void
    {
        if (! $this->db->tableExists('certification_applications') || ! $this->db->tableExists('application_questions')) {
            return;
        }

        $applications = $this->db->table('certification_applications')
            ->select('certification_applications.*, clients.scope, clients.company')
            ->join('clients', 'clients.id = certification_applications.client_id')
            ->get()
            ->getResultArray();

        foreach ($applications as $application) {
            $standards = $this->standardsForApplication($application);
            if (! $this->defaults->hasHaccp($standards)) {
                continue;
            }

            $question = $this->ensureApplicationQuestion((int) $application['id']);
            if ($question === null) {
                continue;
            }

            $answer = $this->defaults->applicationAnswer('haccp_plans_processes', $application, $standards) ?? '1';
            $this->saveAnswerIfEmpty((int) $application['id'], $question, $answer, (int) ($application['created_by'] ?? 0));
        }
    }

    private function backfillApplicationReviews(): void
    {
        if (! $this->db->tableExists('application_reviews')) {
            return;
        }

        $reviews = $this->db->table('application_reviews')
            ->select('application_reviews.*, clients.scope, clients.company, clients.id AS client_id')
            ->join('clients', 'clients.id = application_reviews.client_id')
            ->get()
            ->getResultArray();

        foreach ($reviews as $review) {
            $standards = $this->standardsForClient((int) $review['client_id']);
            if (! $this->defaults->hasHaccp($standards)) {
                continue;
            }

            $payload = json_decode((string) ($review['review_payload'] ?? ''), true) ?: [];
            if (trim((string) ($payload['haccp_plans_processes'] ?? '')) !== '') {
                continue;
            }

            $payload['haccp_plans_processes'] = $this->defaults->applicationAnswer('haccp_plans_processes', $review, $standards) ?? '1';
            $this->db->table('application_reviews')
                ->where('id', (int) $review['id'])
                ->update(['review_payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);
        }
    }

    private function ensureApplicationQuestion(int $applicationId): ?array
    {
        $library = $this->db->table('question_library')
            ->where('question_key', 'haccp_plans_processes')
            ->where('active', 1)
            ->get(1)
            ->getRowArray();

        if ($library === null) {
            return null;
        }

        $existing = $this->db->table('application_questions')
            ->where('application_id', $applicationId)
            ->where('question_key', 'haccp_plans_processes')
            ->get(1)
            ->getRowArray();

        if ($existing !== null) {
            return $existing;
        }

        $payload = [
            'application_id' => $applicationId,
            'question_library_id' => (int) $library['id'],
            'question_key' => 'haccp_plans_processes',
            'question_text' => 'Number of HACCP Studies / Plans',
            'question_type' => 'number',
            'section' => 'Certification Required',
            'display_order' => 505,
            'mandatory' => 0,
            'validation_rules' => null,
            'help_text' => null,
            'standard_codes' => json_encode(['HACCP'], JSON_THROW_ON_ERROR),
        ];
        $this->db->table('application_questions')->insert($payload);
        $payload['id'] = (int) $this->db->insertID();

        return $payload;
    }

    private function saveAnswerIfEmpty(int $applicationId, array $question, string $answer, int $userId): void
    {
        $existing = $this->db->table('application_answers')
            ->where('application_id', $applicationId)
            ->where('application_question_id', (int) $question['id'])
            ->get(1)
            ->getRowArray();

        if ($existing !== null && trim((string) ($existing['answer_text'] ?? '')) !== '') {
            return;
        }

        $payload = [
            'application_id' => $applicationId,
            'application_question_id' => (int) $question['id'],
            'question_library_id' => (int) $question['question_library_id'],
            'answer_text' => $answer,
            'answered_by' => $userId > 0 ? $userId : null,
            'answered_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing === null) {
            $this->db->table('application_answers')->insert($payload);
            return;
        }

        $this->db->table('application_answers')->where('id', (int) $existing['id'])->update($payload);
    }

    private function standardsForApplication(array $application): array
    {
        $rows = $this->db->table('application_selected_standards')
            ->select('standard_code')
            ->where('application_id', (int) $application['id'])
            ->get()
            ->getResultArray();

        if ($rows !== []) {
            return $rows;
        }

        return $this->standardsForClient((int) $application['client_id']);
    }

    private function standardsForClient(int $clientId): array
    {
        return $this->db->table('client_standards')
            ->select('standards.code AS standard_code')
            ->join('standards', 'standards.id = client_standards.standard_id')
            ->where('client_standards.client_id', $clientId)
            ->get()
            ->getResultArray();
    }
}
