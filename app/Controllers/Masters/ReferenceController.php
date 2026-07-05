<?php

namespace App\Controllers\Masters;

use App\Controllers\BaseController;
use App\Models\FoodChainCategoryModel;
use App\Models\IafCodeModel;
use App\Models\MedicalDeviceCategoryModel;
use App\Models\NaceCodeModel;
use App\Services\AuditLogger;
use CodeIgniter\Model;

class ReferenceController extends BaseController
{
    private AuditLogger $auditLogger;

    private array $types = [
        'iaf' => [
            'title' => 'IAF Codes',
            'subtitle' => 'Industry accreditation sectors and risk levels',
            'model' => IafCodeModel::class,
            'table' => 'iaf_codes',
            'fields' => ['code', 'title', 'risk_level', 'active'],
        ],
        'nace' => [
            'title' => 'NACE Codes',
            'subtitle' => 'Economic activity classification codes',
            'model' => NaceCodeModel::class,
            'table' => 'nace_codes',
            'fields' => ['code', 'title', 'active'],
        ],
        'food' => [
            'title' => 'Food Chain Categories',
            'subtitle' => 'Food safety certification categories',
            'model' => FoodChainCategoryModel::class,
            'table' => 'food_chain_categories',
            'fields' => ['code', 'title', 'description', 'active'],
        ],
        'medical' => [
            'title' => 'Medical Device Categories',
            'subtitle' => 'Medical device certification categories',
            'model' => MedicalDeviceCategoryModel::class,
            'table' => 'medical_device_categories',
            'fields' => ['code', 'title', 'description', 'active'],
        ],
    ];

    public function __construct()
    {
        $this->auditLogger = new AuditLogger();
    }

    public function index(string $type)
    {
        $config = $this->typeConfig($type);

        return view('masters/references/index', [
            'title' => $config['title'],
            'pageTitle' => $config['title'],
            'pageSubtitle' => $config['subtitle'],
            'type' => $type,
            'config' => $config,
            'records' => $this->model($config)->orderBy('code', 'ASC')->findAll(),
        ]);
    }

    public function new(string $type)
    {
        $config = $this->typeConfig($type);

        return view('masters/references/form', [
            'title' => 'New ' . $config['title'],
            'pageTitle' => 'New ' . $config['title'],
            'pageSubtitle' => $config['subtitle'],
            'type' => $type,
            'config' => $config,
            'record' => $this->blank($config),
            'action' => site_url('masters/references/' . $type),
        ]);
    }

    public function create(string $type)
    {
        $config = $this->typeConfig($type);

        if (! $this->validate($this->rules($config))) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = $this->payload($config);
        $id = (int) $this->model($config)->insert($data);
        $this->auditLogger->record('create', $config['table'], $config['table'], $id, null, $data);

        return redirect()->to('/masters/references/' . $type)->with('success', 'Reference created.');
    }

    public function edit(string $type, int $id)
    {
        $config = $this->typeConfig($type);
        $record = $this->model($config)->find($id);

        if ($record === null) {
            return redirect()->to('/masters/references/' . $type)->with('error', 'Reference not found.');
        }

        return view('masters/references/form', [
            'title' => 'Edit ' . $config['title'],
            'pageTitle' => 'Edit ' . $config['title'],
            'pageSubtitle' => $record['code'],
            'type' => $type,
            'config' => $config,
            'record' => $record,
            'action' => site_url('masters/references/' . $type . '/' . $id),
        ]);
    }

    public function update(string $type, int $id)
    {
        $config = $this->typeConfig($type);
        $model = $this->model($config);
        $record = $model->find($id);

        if ($record === null) {
            return redirect()->to('/masters/references/' . $type)->with('error', 'Reference not found.');
        }

        if (! $this->validate($this->rules($config))) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = $this->payload($config);
        $model->update($id, $data);
        $this->auditLogger->record('update', $config['table'], $config['table'], $id, $record, $data);

        return redirect()->to('/masters/references/' . $type)->with('success', 'Reference updated.');
    }

    public function deactivate(string $type, int $id)
    {
        $config = $this->typeConfig($type);
        $model = $this->model($config);
        $record = $model->find($id);

        if ($record === null) {
            return redirect()->to('/masters/references/' . $type)->with('error', 'Reference not found.');
        }

        $model->update($id, ['active' => 0]);
        $this->auditLogger->record('delete', $config['table'], $config['table'], $id, $record, ['active' => 0]);

        return redirect()->to('/masters/references/' . $type)->with('success', 'Reference deactivated.');
    }

    private function typeConfig(string $type): array
    {
        if (! isset($this->types[$type])) {
            throw new \InvalidArgumentException('Unknown reference type.');
        }

        return $this->types[$type];
    }

    private function model(array $config): Model
    {
        $class = $config['model'];

        return new $class();
    }

    private function rules(array $config): array
    {
        $rules = [
            'code' => 'required|max_length[30]',
            'title' => 'required|max_length[220]',
        ];

        if (in_array('risk_level', $config['fields'], true)) {
            $rules['risk_level'] = 'permit_empty|max_length[40]';
        }

        return $rules;
    }

    private function payload(array $config): array
    {
        $payload = [
            'code' => trim((string) $this->request->getPost('code')),
            'title' => trim((string) $this->request->getPost('title')),
            'active' => $this->request->getPost('active') === '1' ? 1 : 0,
        ];

        if (in_array('risk_level', $config['fields'], true)) {
            $payload['risk_level'] = trim((string) $this->request->getPost('risk_level')) ?: null;
        }

        if (in_array('description', $config['fields'], true)) {
            $payload['description'] = trim((string) $this->request->getPost('description')) ?: null;
        }

        return $payload;
    }

    private function blank(array $config): array
    {
        $record = [
            'code' => '',
            'title' => '',
            'active' => 1,
        ];

        if (in_array('risk_level', $config['fields'], true)) {
            $record['risk_level'] = '';
        }

        if (in_array('description', $config['fields'], true)) {
            $record['description'] = '';
        }

        return $record;
    }
}
