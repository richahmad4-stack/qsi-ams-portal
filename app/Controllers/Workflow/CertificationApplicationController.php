<?php

namespace App\Controllers\Workflow;

use App\Controllers\BaseController;
use App\Models\ApplicationAnswerModel;
use App\Models\ApplicationAttachmentModel;
use App\Models\ApplicationQuestionModel;
use App\Models\ApplicationSelectedStandardModel;
use App\Models\CertificationApplicationModel;
use App\Models\ClientModel;
use App\Services\AuditLogger;
use Config\Database;

class CertificationApplicationController extends BaseController
{
    private CertificationApplicationModel $applications;
    private ApplicationQuestionModel $applicationQuestions;
    private ApplicationAnswerModel $answers;
    private ApplicationSelectedStandardModel $selectedStandards;
    private ApplicationAttachmentModel $attachments;
    private ClientModel $clients;
    private AuditLogger $auditLogger;

    public function __construct()
    {
        $this->applications = new CertificationApplicationModel();
        $this->applicationQuestions = new ApplicationQuestionModel();
        $this->answers = new ApplicationAnswerModel();
        $this->selectedStandards = new ApplicationSelectedStandardModel();
        $this->attachments = new ApplicationAttachmentModel();
        $this->clients = new ClientModel();
        $this->auditLogger = new AuditLogger();
    }

    public function edit(int $clientId)
    {
        $tenantId = (int) session()->get('tenant_id');
        $client = $this->client($tenantId, $clientId);

        if ($client === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        $application = $this->application($tenantId, $clientId);
        $selected = $this->selectedStandardRows((int) $application['id']);
        if ($selected === []) {
            $selected = $this->clientStandardRows($clientId);
            $this->syncSelectedStandards((int) $application['id'], array_column($selected, 'standard_id'));
            $selected = $this->selectedStandardRows((int) $application['id']);
        }

        $questions = $this->syncApplicationQuestions((int) $application['id'], array_column($selected, 'standard_code'));

        return view('workflow/application/form', [
            'title' => 'Certification Application Form',
            'pageTitle' => 'Certification Application Form',
            'pageSubtitle' => $client['company'],
            'client' => $client,
            'application' => $application,
            'standards' => $this->allStandards(),
            'selectedStandardIds' => array_map('intval', array_column($selected, 'standard_id')),
            'questionsBySection' => $this->groupQuestions($questions),
            'answers' => $this->answersByQuestion((int) $application['id']),
            'attachmentsByQuestion' => $this->attachmentsByQuestion((int) $application['id']),
        ]);
    }

    public function save(int $clientId)
    {
        $tenantId = (int) session()->get('tenant_id');
        $client = $this->client($tenantId, $clientId);

        if ($client === null) {
            return redirect()->to('/workflow/certification')->with('error', 'Client not found.');
        }

        $application = $this->application($tenantId, $clientId);
        $selectedIds = array_values(array_filter(array_map('intval', (array) $this->request->getPost('standard_ids'))));
        if ($selectedIds === []) {
            return redirect()->back()->withInput()->with('error', 'Select at least one standard.');
        }

        $this->syncSelectedStandards((int) $application['id'], $selectedIds);
        $this->syncClientStandards($clientId, $selectedIds);
        $selected = $this->selectedStandardRows((int) $application['id']);
        $questions = $this->syncApplicationQuestions((int) $application['id'], array_column($selected, 'standard_code'));
        $postedAnswers = (array) $this->request->getPost('answers');

        foreach ($questions as $question) {
            if ((int) $question['mandatory'] !== 1 || $question['question_type'] === 'file') {
                continue;
            }

            $value = trim((string) ($postedAnswers[$question['id']] ?? ''));
            if ($value === '') {
                return redirect()->back()->withInput()->with('error', $question['question_text'] . ' is required.');
            }
        }

        foreach ($questions as $question) {
            if ($question['question_type'] === 'file') {
                $this->storeUpload((int) $application['id'], $question);
                continue;
            }

            $value = $postedAnswers[$question['id']] ?? '';
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $this->saveAnswer((int) $application['id'], $question, trim((string) $value));
        }

        $status = $this->request->getPost('submit_application') === '1' ? 'submitted' : 'draft';
        $payload = [
            'status' => $status,
            'submitted_at' => $status === 'submitted' ? date('Y-m-d H:i:s') : ($application['submitted_at'] ?? null),
            'declaration_name' => trim((string) $this->request->getPost('declaration_name')),
            'declaration_position' => trim((string) $this->request->getPost('declaration_position')),
            'declaration_date' => (string) ($this->request->getPost('declaration_date') ?: date('Y-m-d')),
            'cb_review_status' => trim((string) $this->request->getPost('cb_review_status')) ?: null,
            'cb_review_notes' => trim((string) $this->request->getPost('cb_review_notes')) ?: null,
            'reviewed_by' => $this->request->getPost('cb_review_status') ? (int) session()->get('user_id') : ($application['reviewed_by'] ?? null),
            'reviewed_at' => $this->request->getPost('cb_review_status') ? date('Y-m-d H:i:s') : ($application['reviewed_at'] ?? null),
        ];

        $this->applications->update((int) $application['id'], $payload);
        $this->updateClientFromAnswers($clientId, $questions, $postedAnswers);
        $this->auditLogger->record('update', 'certification_applications', 'certification_applications', (int) $application['id'], $application, $payload);

        return redirect()->to('/workflow/certification/' . $clientId . '/application')->with('success', 'Certification application saved.');
    }

    private function client(int $tenantId, int $clientId): ?array
    {
        $client = $this->clients->find($clientId);

        return $client !== null && (int) $client['tenant_id'] === $tenantId ? $client : null;
    }

    private function application(int $tenantId, int $clientId): array
    {
        $application = $this->applications
            ->where('tenant_id', $tenantId)
            ->where('client_id', $clientId)
            ->orderBy('id', 'DESC')
            ->first();

        if ($application !== null) {
            return $application;
        }

        $payload = [
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'application_number' => 'APP-' . date('Ymd') . '-' . str_pad((string) $clientId, 5, '0', STR_PAD_LEFT),
            'status' => 'draft',
            'declaration_date' => date('Y-m-d'),
            'created_by' => (int) session()->get('user_id'),
        ];
        $id = (int) $this->applications->insert($payload);
        $payload['id'] = $id;
        $this->auditLogger->record('create', 'certification_applications', 'certification_applications', $id, null, $payload);

        return $payload;
    }

    private function allStandards(): array
    {
        return Database::connect()->table('standards')
            ->where('active', 1)
            ->orderBy('code', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function clientStandardRows(int $clientId): array
    {
        return Database::connect()->table('client_standards')
            ->select('client_standards.standard_id, standards.code AS standard_code')
            ->join('standards', 'standards.id = client_standards.standard_id')
            ->where('client_standards.client_id', $clientId)
            ->get()
            ->getResultArray();
    }

    private function selectedStandardRows(int $applicationId): array
    {
        return $this->selectedStandards
            ->where('application_id', $applicationId)
            ->orderBy('standard_code', 'ASC')
            ->findAll();
    }

    private function syncSelectedStandards(int $applicationId, array $standardIds): void
    {
        $db = Database::connect();
        $db->table('application_selected_standards')->where('application_id', $applicationId)->delete();

        if ($standardIds === []) {
            return;
        }

        $standards = $db->table('standards')->whereIn('id', $standardIds)->get()->getResultArray();
        foreach ($standards as $standard) {
            $this->selectedStandards->insert([
                'application_id' => $applicationId,
                'standard_id' => (int) $standard['id'],
                'standard_code' => (string) $standard['code'],
            ]);
        }
    }

    private function syncClientStandards(int $clientId, array $standardIds): void
    {
        $db = Database::connect();
        foreach ($standardIds as $standardId) {
            $exists = $db->table('client_standards')
                ->where('client_id', $clientId)
                ->where('standard_id', $standardId)
                ->countAllResults();

            if ($exists > 0) {
                continue;
            }

            $db->table('client_standards')->insert([
                'client_id' => $clientId,
                'standard_id' => $standardId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function syncApplicationQuestions(int $applicationId, array $standardCodes): array
    {
        $db = Database::connect();
        $selected = array_map('strtoupper', $standardCodes);
        $includedKeys = [];
        $library = $db->table('question_library')
            ->where('active', 1)
            ->orderBy('section', 'ASC')
            ->orderBy('display_order', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($library as $question) {
            if ($this->excludeFromApplication($question)) {
                continue;
            }

            $standards = array_map('strtoupper', json_decode((string) $question['applicable_standards'], true) ?: []);
            if (! in_array('COMMON', $standards, true) && array_intersect($standards, $selected) === []) {
                continue;
            }

            $includedKeys[] = $question['question_key'];
            $payload = [
                'application_id' => $applicationId,
                'question_library_id' => (int) $question['id'],
                'question_key' => $question['question_key'],
                'question_text' => $question['question_text'],
                'question_type' => $question['question_type'],
                'section' => $question['section'],
                'display_order' => (int) $question['display_order'],
                'mandatory' => (int) $question['mandatory'],
                'validation_rules' => $question['validation_rules'],
                'help_text' => $question['help_text'],
                'standard_codes' => json_encode($standards, JSON_THROW_ON_ERROR),
            ];

            $existing = $this->applicationQuestions
                ->where('application_id', $applicationId)
                ->where('question_key', $question['question_key'])
                ->first();

            if ($existing === null) {
                $this->applicationQuestions->insert($payload);
            } else {
                $this->applicationQuestions->update((int) $existing['id'], $payload);
            }
        }

        $questionTable = $db->table('application_questions')->where('application_id', $applicationId);
        if ($includedKeys === []) {
            $questionTable->delete();
        } else {
            $questionTable->whereNotIn('question_key', $includedKeys)->delete();
        }

        return $this->applicationQuestions
            ->where('application_id', $applicationId)
            ->whereNotIn('section', $this->excludedApplicationSections())
            ->where('question_type !=', 'file')
            ->orderBy('section', 'ASC')
            ->orderBy('display_order', 'ASC')
            ->findAll();
    }

    private function excludeFromApplication(array $question): bool
    {
        return $this->applicationSectionExcluded((string) ($question['section'] ?? ''))
            || (string) ($question['question_type'] ?? '') === 'file';
    }

    private function applicationSectionExcluded(string $section): bool
    {
        return in_array($section, $this->excludedApplicationSections(), true)
            || str_ends_with(strtoupper(trim($section)), 'SPECIFIC QUESTIONS');
    }

    private function excludedApplicationSections(): array
    {
        return [
            'Supporting Documents',
            'Declaration',
            'HACCP Specific Questions',
        ];
    }

    private function saveAnswer(int $applicationId, array $question, string $value): void
    {
        $existing = $this->answers
            ->where('application_id', $applicationId)
            ->where('application_question_id', (int) $question['id'])
            ->first();

        $payload = [
            'application_id' => $applicationId,
            'application_question_id' => (int) $question['id'],
            'question_library_id' => (int) $question['question_library_id'],
            'answer_text' => $value,
            'answered_by' => (int) session()->get('user_id'),
            'answered_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing === null) {
            $this->answers->insert($payload);
        } else {
            $this->answers->update((int) $existing['id'], $payload);
        }
    }

    private function storeUpload(int $applicationId, array $question): void
    {
        $file = $this->request->getFile('file_' . $question['id']);
        if ($file === null || ! $file->isValid() || $file->hasMoved()) {
            return;
        }

        $directory = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'applications' . DIRECTORY_SEPARATOR . 'application_' . $applicationId;
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $name = $file->getRandomName();
        $file->move($directory, $name);
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        $this->attachments->insert([
            'application_id' => $applicationId,
            'application_question_id' => (int) $question['id'],
            'uploaded_by' => (int) session()->get('user_id'),
            'category' => $question['question_key'],
            'original_filename' => $file->getClientName(),
            'storage_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ]);

        $this->saveAnswer($applicationId, $question, $file->getClientName());
    }

    private function answersByQuestion(int $applicationId): array
    {
        $rows = $this->answers->where('application_id', $applicationId)->findAll();
        $answers = [];
        foreach ($rows as $row) {
            $answers[(int) $row['application_question_id']] = (string) ($row['answer_text'] ?? '');
        }

        return $answers;
    }

    private function attachmentsByQuestion(int $applicationId): array
    {
        $rows = $this->attachments->where('application_id', $applicationId)->orderBy('id', 'DESC')->findAll();
        $attachments = [];
        foreach ($rows as $row) {
            $attachments[(int) $row['application_question_id']][] = $row;
        }

        return $attachments;
    }

    private function groupQuestions(array $questions): array
    {
        $grouped = [];
        foreach ($questions as $question) {
            $grouped[$question['section']][] = $question;
        }

        return $grouped;
    }

    private function updateClientFromAnswers(int $clientId, array $questions, array $postedAnswers): void
    {
        $map = [
            'company_name' => 'company',
            'legal_name' => 'legal_name',
            'address' => 'address',
            'country' => 'country',
            'city' => 'city',
            'website' => 'website',
            'contact_person' => 'contact_person',
            'designation' => 'designation',
            'email' => 'email',
            'phone' => 'phone',
            'scope_of_certification' => 'scope',
            'employee_count' => 'employee_count',
            'permanent_employees' => 'permanent_employees',
            'temporary_employees' => 'temporary_employees',
            'seasonal_operations' => 'seasonal_operations',
            'number_of_sites' => 'number_of_sites',
            'outsourced_processes' => 'outsourced_processes',
        ];
        $payload = [];

        foreach ($questions as $question) {
            $field = $map[$question['question_key']] ?? null;
            if ($field === null) {
                continue;
            }

            $value = trim((string) ($postedAnswers[$question['id']] ?? ''));
            if ($value !== '') {
                $payload[$field] = $value;
            }
        }

        if ($payload !== []) {
            $this->clients->update($clientId, $payload);
        }
    }
}
