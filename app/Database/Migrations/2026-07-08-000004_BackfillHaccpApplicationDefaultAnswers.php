<?php

namespace App\Database\Migrations;

use App\Services\CertificationApplicationDefaults;
use CodeIgniter\Database\Migration;

class BackfillHaccpApplicationDefaultAnswers extends Migration
{
    private CertificationApplicationDefaults $defaults;

    public function up(): void
    {
        if (! $this->db->tableExists('certification_applications')) {
            return;
        }

        $this->defaults = new CertificationApplicationDefaults();
        $this->db->transStart();
        $this->refreshApplicationQuestionLabels();
        $this->replaceKnownPlaceholders();
        $this->db->transComplete();
    }

    public function down(): void
    {
    }

    private function refreshApplicationQuestionLabels(): void
    {
        $questions = $this->db->table('question_library')
            ->select('question_key, question_text')
            ->whereIn('question_key', [
                'legal_statutory_requirements',
                'product_process_risks',
                'technical_issues',
                'safety_requirements',
                'technological_regulatory_context',
                'last_management_review_meeting_conducted',
            ])
            ->get()
            ->getResultArray();

        foreach ($questions as $question) {
            $this->db->table('application_questions')
                ->where('question_key', (string) $question['question_key'])
                ->update(['question_text' => (string) $question['question_text']]);
        }
    }

    private function replaceKnownPlaceholders(): void
    {
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

            foreach ([
                'legal_statutory_requirements',
                'product_process_risks',
                'technical_issues',
                'safety_requirements',
                'technological_regulatory_context',
                'products',
                'services',
                'processes',
                'outsourced_processes',
            ] as $key) {
                $answer = $this->defaults->applicationAnswer($key, $application, $standards);
                if ($answer === null || trim($answer) === '') {
                    continue;
                }

                $question = $this->db->table('application_questions')
                    ->where('application_id', (int) $application['id'])
                    ->where('question_key', $key)
                    ->get(1)
                    ->getRowArray();

                if ($question === null) {
                    continue;
                }

                $existing = $this->db->table('application_answers')
                    ->where('application_id', (int) $application['id'])
                    ->where('application_question_id', (int) $question['id'])
                    ->get(1)
                    ->getRowArray();

                if ($existing === null) {
                    $this->db->table('application_answers')->insert([
                        'application_id' => (int) $application['id'],
                        'application_question_id' => (int) $question['id'],
                        'question_library_id' => (int) $question['question_library_id'],
                        'answer_text' => $answer,
                        'answered_by' => $application['created_by'] ?? null,
                        'answered_at' => date('Y-m-d H:i:s'),
                    ]);
                    continue;
                }

                if ($this->isPlaceholder((string) ($existing['answer_text'] ?? ''))) {
                    $this->db->table('application_answers')->where('id', (int) $existing['id'])->update([
                        'answer_text' => $answer,
                        'answered_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    private function isPlaceholder(string $answer): bool
    {
        $normalized = strtolower(trim($answer));

        return $normalized === ''
            || str_starts_with($normalized, 'demo response:')
            || str_contains($normalized, 'to be verified during')
            || str_contains($normalized, 'applicable legal, statutory, regulatory and customer requirements');
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

        return $this->db->table('client_standards')
            ->select('standards.code AS standard_code')
            ->join('standards', 'standards.id = client_standards.standard_id')
            ->where('client_standards.client_id', (int) $application['client_id'])
            ->get()
            ->getResultArray();
    }
}
