<?php

namespace App\Controllers\Masters;

use App\Controllers\BaseController;
use App\Models\StandardModel;
use App\Support\CertificationBaseline;

class StandardController extends BaseController
{
    private StandardModel $standards;

    public function __construct()
    {
        $this->standards = new StandardModel();
    }

    public function index()
    {
        return view('masters/standards/index', [
            'title' => 'Standards',
            'pageTitle' => 'Standards',
            'pageSubtitle' => 'Certification schemes and standard versions',
            'standards' => $this->standards->orderBy('code', 'ASC')->findAll(),
        ]);
    }

    public function new()
    {
        return $this->catalogueLockedResponse();
    }

    public function create()
    {
        return $this->catalogueLockedResponse();
    }

    public function edit(int $id)
    {
        $standard = $this->standards->find($id);

        if ($standard === null) {
            return redirect()->to('/masters/standards')->with('error', 'Standard not found.');
        }

        return $this->catalogueLockedResponse((string) $standard['code']);
    }

    public function update(int $id)
    {
        $standard = $this->standards->find($id);

        if ($standard === null) {
            return redirect()->to('/masters/standards')->with('error', 'Standard not found.');
        }

        return $this->catalogueLockedResponse((string) $standard['code']);
    }

    public function deactivate(int $id)
    {
        $standard = $this->standards->find($id);

        if ($standard === null) {
            return redirect()->to('/masters/standards')->with('error', 'Standard not found.');
        }

        return $this->catalogueLockedResponse((string) $standard['code']);
    }

    private function catalogueLockedResponse(?string $code = null)
    {
        $message = $code !== null && ! CertificationBaseline::isApproved($code)
            ? 'Unsupported standards are retained for history only and cannot be activated or edited.'
            : 'The approved certification baseline catalogue is controlled and locked.';

        return redirect()->to('/masters/standards')->with('error', $message);
    }
}
