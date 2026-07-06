<?php

namespace App\Controllers\Automation;

use App\Controllers\BaseController;
use App\Services\CycleAutomationService;
use RuntimeException;

class CycleGeneratorController extends BaseController
{
    private CycleAutomationService $automation;

    public function __construct()
    {
        $this->automation = new CycleAutomationService();
    }

    public function index()
    {
        if (! $this->isAllowed()) {
            return redirect()->to('/dashboard')->with('error', 'Only Super User or Admin can use Cycle Builder.');
        }

        return view('automation/cycle_form', [
            'title' => 'Cycle Builder',
            'pageTitle' => 'Cycle Builder',
            'pageSubtitle' => 'Prepare a full certification cycle from client information',
            'standards' => $this->automation->standards(),
            'iafCodes' => $this->automation->iafCodes(),
            'foodCategories' => $this->automation->foodCategories(),
            'medicalCategories' => $this->automation->medicalCategories(),
            'input' => [],
        ]);
    }

    public function preview()
    {
        if (! $this->isAllowed()) {
            return redirect()->to('/dashboard')->with('error', 'Only Super User or Admin can use Cycle Builder.');
        }

        try {
            $preview = $this->automation->preview(
                $this->request->getPost(),
                (int) session()->get('tenant_id'),
                (int) session()->get('user_id')
            );
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return view('automation/cycle_preview', [
            'title' => 'Cycle Builder Preview',
            'pageTitle' => 'Cycle Builder Preview',
            'pageSubtitle' => $preview['input']['client_name'],
            'preview' => $preview,
            'encodedPreview' => base64_encode(json_encode($preview, JSON_THROW_ON_ERROR)),
        ]);
    }

    public function generate()
    {
        if (! $this->isAllowed()) {
            return redirect()->to('/dashboard')->with('error', 'Only Super User or Admin can use Cycle Builder.');
        }

        $encoded = (string) $this->request->getPost('preview_payload');
        $preview = json_decode(base64_decode($encoded, true) ?: '', true);
        if (! is_array($preview)) {
            return redirect()->to('/automation/cycle-generator')->with('error', 'Preview payload expired or invalid. Please preview again.');
        }

        try {
            $result = $this->automation->generate(
                $preview,
                (int) session()->get('tenant_id'),
                (int) session()->get('user_id')
            );
        } catch (RuntimeException $exception) {
            return redirect()->to('/automation/cycle-generator')->with('error', $exception->getMessage());
        }

        return redirect()
            ->to('/workflow/certification/' . $result['client_id'])
            ->with('success', 'Full certification cycle prepared.');
    }

    public function upload()
    {
        if (! $this->isAllowed()) {
            return redirect()->to('/dashboard')->with('error', 'Only Super User or Admin can use Cycle Builder.');
        }

        $file = $this->request->getFile('cycle_file');
        if ($file === null || ! $file->isValid()) {
            return redirect()->to('/automation/cycle-generator')->with('error', 'Please upload a valid CSV or XLSX file.');
        }

        try {
            $result = $this->automation->importBatch(
                $file,
                (int) session()->get('tenant_id'),
                (int) session()->get('user_id')
            );
        } catch (RuntimeException $exception) {
            return redirect()->to('/automation/cycle-generator')->with('error', $exception->getMessage());
        }

        return view('automation/cycle_upload_result', [
            'title' => 'Batch Upload Result',
            'pageTitle' => 'Batch Upload Result',
            'pageSubtitle' => 'Certification files prepared from spreadsheet',
            'result' => $result,
        ]);
    }

    public function template()
    {
        if (! $this->isAllowed()) {
            return redirect()->to('/dashboard')->with('error', 'Only Super User or Admin can use Cycle Builder.');
        }

        $headers = [
            'client_name', 'contact_person', 'designation', 'email', 'phone', 'client_address', 'scope',
            'standards', 'iaf_code', 'food_category', 'medical_category', 'employee_count', 'number_of_sites',
            'certificate_issue_date', 'certificate_expiry_date', 'certification_status', 'risk_category',
            'current_cycle_stage', 'ncr_mode', 'special_notes', 'application_review_notes', 'audit_plan_notes',
            'audit_evidence_summary', 'technical_review_notes', 'decision_basis',
        ];
        $sample = [
            'ABC Company', 'Client Representative', 'Management Representative', 'client@example.com', '+966500000000',
            'Riyadh, Saudi Arabia', 'Catering services', 'HACCP;ISO 9001:2015', '', 'C', '', '30', '1',
            date('Y-m-d'), '', 'certified', 'medium', 'auto', 'none', '', '', '', '', '', '',
        ];
        $csv = implode(',', $headers) . "\r\n" . implode(',', array_map(static fn (string $value): string => '"' . str_replace('"', '""', $value) . '"', $sample)) . "\r\n";

        return $this->response
            ->setHeader('Content-Type', 'text/csv')
            ->setHeader('Content-Disposition', 'attachment; filename="cycle-builder-template.csv"')
            ->setBody($csv);
    }

    private function isAllowed(): bool
    {
        $roles = (array) session()->get('role_codes');

        return in_array('super_admin', $roles, true) || in_array('administrator', $roles, true);
    }
}
