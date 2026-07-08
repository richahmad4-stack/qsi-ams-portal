<?php

namespace App\Database\Migrations;

use App\Services\CertificationApplicationDefaults;
use CodeIgniter\Database\Migration;

class RefineHaccpApplicationQuestions extends Migration
{
    private CertificationApplicationDefaults $defaults;

    public function up(): void
    {
        if (! $this->db->tableExists('question_library')) {
            return;
        }

        $this->defaults = new CertificationApplicationDefaults();
        $this->db->transStart();
        $this->removePreferredAuditQuestions();
        $this->upsertQuestions();
        $this->backfillApplicationAnswers();
        $this->backfillApplicationReviewPayloads();
        $this->db->transComplete();
    }

    public function down(): void
    {
        if (! $this->db->tableExists('question_library')) {
            return;
        }

        $this->db->table('question_library')
            ->whereIn('question_key', ['preferred_audit_dates', 'preferred_auditor'])
            ->update(['active' => 1]);
    }

    private function removePreferredAuditQuestions(): void
    {
        $keys = [
            'preferred_audit_dates',
            'preferred_auditor',
            'cycle_audit_preferences_preferred_audit_dates',
            'cycle_audit_preferences_preferred_auditor',
        ];

        if ($this->db->tableExists('application_questions')) {
            $this->db->table('application_questions')
                ->groupStart()
                ->whereIn('question_key', $keys)
                ->orWhereIn('question_text', ['Preferred Audit Dates', 'Preferred Auditor'])
                ->groupEnd()
                ->delete();
        }

        $this->db->table('question_library')
            ->whereIn('question_key', $keys)
            ->update(['active' => 0]);
    }

    private function upsertQuestions(): void
    {
        $rows = [
            [
                'question_key' => 'legal_statutory_requirements',
                'question_text' => 'Applicable Legal and Regulatory Requirement',
                'question_type' => 'textarea',
                'applicable_standards' => ['COMMON'],
                'mandatory' => 0,
                'section' => 'Certification Required',
                'display_order' => 500,
                'validation_rules' => null,
            ],
            [
                'question_key' => 'last_management_review_meeting_conducted',
                'question_text' => 'Last management review meeting conducted?',
                'question_type' => 'select',
                'applicable_standards' => ['COMMON'],
                'mandatory' => 0,
                'section' => 'Management System Readiness',
                'display_order' => 565,
                'validation_rules' => ['options' => ['Yes', 'No', 'Partially', 'Not Applicable']],
            ],
            [
                'question_key' => 'product_process_risks',
                'question_text' => 'Risks associated with products, processes or activities',
                'question_type' => 'textarea',
                'applicable_standards' => ['HACCP'],
                'mandatory' => 0,
                'section' => 'Certification Required',
                'display_order' => 505,
                'validation_rules' => null,
            ],
            [
                'question_key' => 'technical_issues',
                'question_text' => '1a. Analysis of technical issues arising from the scope',
                'question_type' => 'textarea',
                'applicable_standards' => ['HACCP'],
                'mandatory' => 0,
                'section' => 'Certification Required',
                'display_order' => 506,
                'validation_rules' => null,
            ],
            [
                'question_key' => 'safety_requirements',
                'question_text' => '1b. Safety condition requirements',
                'question_type' => 'textarea',
                'applicable_standards' => ['HACCP'],
                'mandatory' => 0,
                'section' => 'Certification Required',
                'display_order' => 507,
                'validation_rules' => null,
            ],
            [
                'question_key' => 'technological_regulatory_context',
                'question_text' => '1c. Technological and Regulatory Context',
                'question_type' => 'textarea',
                'applicable_standards' => ['HACCP'],
                'mandatory' => 0,
                'section' => 'Certification Required',
                'display_order' => 508,
                'validation_rules' => null,
            ],
        ];

        foreach ($rows as $row) {
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
                    $row['question_key'],
                    $row['question_text'],
                    $row['question_type'],
                    json_encode($row['applicable_standards'], JSON_THROW_ON_ERROR),
                    $row['mandatory'],
                    $row['section'],
                    $row['display_order'],
                    $row['validation_rules'] === null ? null : json_encode($row['validation_rules'], JSON_THROW_ON_ERROR),
                ]
            );
        }
    }

    private function backfillApplicationAnswers(): void
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
            $hasHaccp = $this->defaults->hasHaccp($standards);
            $keys = [
                'language_of_audit',
                'previous_qsi_contact',
                'qsi_contact_details',
                'heard_about_qsi',
                'other_qsi_services',
                'scope_of_certification',
                'products',
                'services',
                'processes',
                'outsourced_processes',
                'management_system_status',
                'implementation_status',
                'internal_audit_conducted',
                'management_review_conducted',
                'last_management_review_meeting_conducted',
            ];

            if ($hasHaccp) {
                array_push($keys, 'legal_statutory_requirements', 'product_process_risks', 'technical_issues', 'safety_requirements', 'technological_regulatory_context');
            }

            foreach ($keys as $key) {
                $answer = $this->defaults->applicationAnswer($key, $application, $standards);
                if ($answer === null || trim($answer) === '') {
                    continue;
                }

                $question = $this->ensureApplicationQuestion((int) $application['id'], $key);
                if ($question === null) {
                    continue;
                }

                $this->saveAnswerIfEmpty((int) $application['id'], $question, $answer, (int) ($application['created_by'] ?? 0));
            }
        }
    }

    private function backfillApplicationReviewPayloads(): void
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
            $defaults = $this->defaults->reviewDefaults($review, $standards);
            if (! $this->defaults->hasHaccp($standards)) {
                continue;
            }

            $payload = json_decode((string) ($review['review_payload'] ?? ''), true) ?: [];
            foreach ($defaults as $key => $value) {
                if (trim((string) ($payload[$key] ?? '')) === '' && trim((string) $value) !== '') {
                    $payload[$key] = $value;
                }
            }

            $this->db->table('application_reviews')
                ->where('id', (int) $review['id'])
                ->update(['review_payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);
        }
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
        if (! $this->db->tableExists('client_standards')) {
            return [];
        }

        return $this->db->table('client_standards')
            ->select('standards.code AS standard_code')
            ->join('standards', 'standards.id = client_standards.standard_id')
            ->where('client_standards.client_id', $clientId)
            ->get()
            ->getResultArray();
    }

    private function ensureApplicationQuestion(int $applicationId, string $key): ?array
    {
        $library = $this->db->table('question_library')
            ->where('question_key', $key)
            ->where('active', 1)
            ->get(1)
            ->getRowArray();

        if ($library === null) {
            return null;
        }

        $existing = $this->db->table('application_questions')
            ->where('application_id', $applicationId)
            ->where('question_key', $key)
            ->get(1)
            ->getRowArray();

        if ($existing !== null) {
            return $existing;
        }

        $payload = [
            'application_id' => $applicationId,
            'question_library_id' => (int) $library['id'],
            'question_key' => (string) $library['question_key'],
            'question_text' => (string) $library['question_text'],
            'question_type' => (string) $library['question_type'],
            'section' => (string) $library['section'],
            'display_order' => (int) $library['display_order'],
            'mandatory' => (int) $library['mandatory'],
            'validation_rules' => $library['validation_rules'],
            'help_text' => $library['help_text'],
            'standard_codes' => (string) $library['applicable_standards'],
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

        if (trim((string) ($existing['answer_text'] ?? '')) === '') {
            $this->db->table('application_answers')->where('id', (int) $existing['id'])->update($payload);
        }
    }
}
