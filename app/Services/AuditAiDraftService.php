<?php

namespace App\Services;

class AuditAiDraftService
{
    private AuditReportNarrativeService $fallback;

    public function __construct()
    {
        $this->fallback = new AuditReportNarrativeService();
    }

    public function conformityNote(array $client, ?array $event, array $clause, array $planItems = [], array $auditTeam = []): array
    {
        $fallbackText = $this->fallback->conformityNote($client, $event, $clause, $planItems, $auditTeam);
        $apiKey = trim((string) (env('OPENAI_API_KEY') ?: getenv('OPENAI_API_KEY')));

        if ($apiKey === '' || ! function_exists('curl_init')) {
            return [
                'source' => 'local',
                'text' => $fallbackText,
            ];
        }

        $prompt = $this->prompt($client, $event, $clause, $planItems, $auditTeam, $fallbackText);
        $generated = $this->callOpenAi($apiKey, $prompt);

        return [
            'source' => $generated === null ? 'local' : 'ai',
            'text' => $generated ?: $fallbackText,
        ];
    }

    private function prompt(array $client, ?array $event, array $clause, array $planItems, array $auditTeam, string $fallbackText): string
    {
        $context = [
            'client' => [
                'company' => $client['company'] ?? '',
                'scope' => $client['scope'] ?? '',
                'employee_count' => $client['employee_count'] ?? '',
            ],
            'audit_event' => $event ?? [],
            'clause' => [
                'standard' => $clause['standard_code'] ?? '',
                'number' => $clause['clause_number'] ?? '',
                'title' => $clause['clause_title'] ?? '',
                'requirement' => $clause['requirement'] ?? '',
                'evidence_examples' => $clause['evidence_examples'] ?? '',
                'auditor_guidance' => $clause['auditor_guidance'] ?? '',
            ],
            'audit_team' => array_map(static fn (array $row): array => [
                'name' => $row['full_name'] ?? '',
                'role' => $row['appointment_role'] ?? '',
            ], $auditTeam),
            'audit_plan_items' => array_map(static fn (array $row): array => [
                'date' => $row['audit_date'] ?? '',
                'time' => trim((string) (($row['start_time'] ?? '') . '-' . ($row['end_time'] ?? ''))),
                'department' => $row['department'] ?? '',
                'process' => $row['process_name'] ?? '',
                'activity' => $row['activity_type'] ?? '',
                'auditor' => $row['auditor_name'] ?? '',
            ], $planItems),
        ];

        return "You are a senior certification body auditor writing a concise audit report conformity note.\n"
            . "Write 90 to 140 words maximum. Use sampled evidence wording, not absolute guarantees. Do not invent exact document numbers, record IDs, dates or names unless provided in the context.\n"
            . "Use these headings exactly: Conformity note, Objective evidence sampled, Auditor remark.\n"
            . "Make the evidence clause-specific and scope-specific. Mention only evidence that is logical for the clause; do not repeat the same generic evidence for every clause.\n"
            . "If evidence is weak or incomplete, write a cautious conformity note and tell the auditor to raise an NC separately instead of creating an NC inside this note.\n"
            . "Context JSON:\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n"
            . "Baseline local draft to improve:\n" . $fallbackText;
    }

    private function callOpenAi(string $apiKey, string $prompt): ?string
    {
        $payload = [
            'model' => (string) (env('OPENAI_REPORT_MODEL') ?: getenv('OPENAI_REPORT_MODEL') ?: 'gpt-4.1-mini'),
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
            'temperature' => 0.2,
            'max_output_tokens' => 450,
        ];

        $curl = curl_init('https://api.openai.com/v1/responses');
        if ($curl === false) {
            return null;
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_TIMEOUT => 25,
        ]);

        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (! is_string($response) || $response === '' || $status < 200 || $status >= 300) {
            return null;
        }

        $json = json_decode($response, true);
        if (! is_array($json)) {
            return null;
        }

        $outputText = trim((string) ($json['output_text'] ?? ''));
        if ($outputText !== '') {
            return $outputText;
        }

        foreach (($json['output'] ?? []) as $output) {
            foreach (($output['content'] ?? []) as $content) {
                $text = trim((string) ($content['text'] ?? ''));
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return null;
    }
}
