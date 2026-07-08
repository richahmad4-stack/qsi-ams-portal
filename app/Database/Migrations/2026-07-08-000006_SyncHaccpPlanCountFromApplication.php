<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SyncHaccpPlanCountFromApplication extends Migration
{
    public function up(): void
    {
        if (
            ! $this->db->tableExists('application_reviews')
            || ! $this->db->tableExists('certification_applications')
            || ! $this->db->tableExists('application_questions')
            || ! $this->db->tableExists('application_answers')
        ) {
            return;
        }

        $reviews = $this->db->table('application_reviews')
            ->select('id, client_id, certification_application_id, review_payload')
            ->get()
            ->getResultArray();

        foreach ($reviews as $review) {
            $applicationId = (int) ($review['certification_application_id'] ?? 0);
            if ($applicationId <= 0) {
                $applicationId = $this->latestApplicationId((int) $review['client_id']);
            }

            $answer = $this->applicationAnswer($applicationId, 'haccp_plans_processes');
            if ($answer === null) {
                continue;
            }

            $payload = json_decode((string) ($review['review_payload'] ?? ''), true) ?: [];
            $payload['haccp_plans_processes'] = $answer;

            $this->db->table('application_reviews')
                ->where('id', (int) $review['id'])
                ->update(['review_payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);
        }
    }

    public function down(): void
    {
    }

    private function latestApplicationId(int $clientId): int
    {
        $row = $this->db->table('certification_applications')
            ->select('id')
            ->where('client_id', $clientId)
            ->orderBy('id', 'DESC')
            ->get(1)
            ->getRowArray();

        return (int) ($row['id'] ?? 0);
    }

    private function applicationAnswer(int $applicationId, string $questionKey): ?string
    {
        if ($applicationId <= 0) {
            return null;
        }

        $row = $this->db->table('application_answers')
            ->select('application_answers.answer_text')
            ->join('application_questions', 'application_questions.id = application_answers.application_question_id')
            ->where('application_answers.application_id', $applicationId)
            ->where('application_questions.question_key', $questionKey)
            ->get(1)
            ->getRowArray();

        $answer = trim((string) ($row['answer_text'] ?? ''));

        return $answer === '' ? null : $answer;
    }
}
