<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$value = static function (string $field) use ($person) {
    $raw = old($field, $person[$field] ?? '');

    if (in_array($field, ['languages', 'countries'], true) && is_string($raw) && str_starts_with($raw, '[')) {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? implode(', ', $decoded) : '';
    }

    return $raw;
};
$types = [
    'staff' => 'Certification Body - Staff',
    'auditor' => 'Certification Body - Auditor',
    'lead_auditor' => 'Certification Body - Lead Auditor',
    'technical_reviewer' => 'Certification Body - Technical Reviewer',
    'decision_maker' => 'Certification Body - Decision Maker',
    'expert' => 'Certification Body - Technical Expert',
    'observer' => 'Certification Body - Observer',
    'client_representative' => 'Client - Representative',
];
$statuses = ['pending', 'approved', 'suspended', 'expired'];
?>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>
    <section class="panel">
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label" for="full_name">Full name</label>
                <input id="full_name" name="full_name" class="form-control" value="<?= esc($value('full_name')) ?>" required maxlength="180">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="email">Email</label>
                <input id="email" name="email" type="email" class="form-control" value="<?= esc($value('email')) ?>" maxlength="190">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="phone">Phone</label>
                <input id="phone" name="phone" class="form-control" value="<?= esc($value('phone')) ?>" maxlength="50">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="personnel_type">Type</label>
                <select id="personnel_type" name="personnel_type" class="form-select">
                    <?php foreach ($types as $type => $label): ?>
                        <option value="<?= esc($type) ?>" <?= $value('personnel_type') === $type ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Use Client - Representative only for client-side users. CB staff, auditors, reviewers and decision makers belong to us.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="approval_status">Approval status</label>
                <select id="approval_status" name="approval_status" class="form-select">
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= esc($status) ?>" <?= $value('approval_status') === $status ? 'selected' : '' ?>><?= esc(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="client_id">Linked client</label>
                <select id="client_id" name="client_id" class="form-select">
                    <option value="">Not linked</option>
                    <?php foreach (($clients ?? []) as $client): ?>
                        <option value="<?= esc((string) $client['id']) ?>" <?= (string) $value('client_id') === (string) $client['id'] ? 'selected' : '' ?>>
                            <?= esc($client['company']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Used for Client - Representative records. Certification Body personnel are not linked to a client.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="languages">Languages</label>
                <input id="languages" name="languages" class="form-control" value="<?= esc($value('languages')) ?>" placeholder="English, Arabic">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="countries">Countries</label>
                <input id="countries" name="countries" class="form-control" value="<?= esc($value('countries')) ?>" placeholder="Saudi Arabia, UAE">
            </div>
            <div class="col-12">
                <label class="form-label" for="experience_summary">Experience summary</label>
                <textarea id="experience_summary" name="experience_summary" class="form-control" rows="4"><?= esc($value('experience_summary')) ?></textarea>
            </div>
        </div>
    </section>

    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i>
            Save
        </button>
        <a class="btn btn-outline-secondary" href="<?= site_url('masters/personnel') ?>">Cancel</a>
    </div>
</form>
<?= $this->endSection() ?>
