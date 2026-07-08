<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-secondary" href="<?= site_url('masters/clients') ?>">
        <i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i>
        Clients
    </a>
    <a class="btn btn-primary" href="<?= site_url('masters/clients/' . $client['id'] . '/edit') ?>">
        <i class="fa-solid fa-pen me-2" aria-hidden="true"></i>
        Edit profile
    </a>
</div>

<section class="panel mb-3">
    <div class="row g-3">
        <div class="col-md-3">
            <div class="text-secondary small">Status</div>
            <div class="fw-semibold"><?= esc(str_replace('_', ' ', $client['certification_status'])) ?></div>
        </div>
        <div class="col-md-3">
            <div class="text-secondary small">Contact</div>
            <div class="fw-semibold"><?= esc($client['contact_person'] ?? '') ?></div>
        </div>
        <div class="col-md-3">
            <div class="text-secondary small">Email</div>
            <div class="fw-semibold"><?= esc($client['email'] ?? '') ?></div>
        </div>
        <div class="col-md-3">
            <div class="text-secondary small">Sites</div>
            <div class="fw-semibold"><?= esc((string) $client['number_of_sites']) ?></div>
        </div>
        <div class="col-md-3">
            <div class="text-secondary small">Certificate logo</div>
            <?php if (! empty($client['client_logo_path'])): ?>
                <img src="<?= site_url('masters/clients/' . $client['id'] . '/logo') ?>" alt="Client logo" style="max-width: 130px; max-height: 46px; object-fit: contain;">
            <?php else: ?>
                <div class="fw-semibold">Not uploaded</div>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="row g-3">
    <div class="col-xl-6">
        <section class="panel h-100">
            <div class="panel-title">Requested standards</div>
            <form method="post" action="<?= site_url('masters/clients/' . $client['id'] . '/standards') ?>" class="row g-2 mb-3">
                <?= csrf_field() ?>
                <div class="col-md-6">
                    <select name="standard_id" class="form-select" required>
                        <option value="">Standard</option>
                        <?php foreach ($standards as $standard): ?>
                            <option value="<?= esc((string) $standard['id']) ?>"><?= esc($standard['code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="iaf_code_id" class="form-select">
                        <option value="">IAF</option>
                        <?php foreach ($iafCodes as $code): ?><option value="<?= esc((string) $code['id']) ?>"><?= esc($code['code']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="nace_code_id" class="form-select">
                        <option value="">NACE</option>
                        <?php foreach ($naceCodes as $code): ?><option value="<?= esc((string) $code['id']) ?>"><?= esc($code['code']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <select name="food_chain_category_id" class="form-select">
                        <option value="">Food category</option>
                        <?php foreach ($foodCategories as $category): ?><option value="<?= esc((string) $category['id']) ?>"><?= esc($category['code']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <select name="medical_device_category_id" class="form-select">
                        <option value="">Medical category</option>
                        <?php foreach ($medicalCategories as $category): ?><option value="<?= esc((string) $category['id']) ?>"><?= esc($category['code']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <textarea name="scope" class="form-control" rows="2" placeholder="Scope for this standard"></textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-sm btn-primary" type="submit">
                        <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>
                        Add standard
                    </button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Standard</th><th>Codes</th><th>Scope</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($clientStandards as $row): ?>
                        <tr>
                            <td><?= esc($row['standard_code']) ?></td>
                            <td><?= esc(trim(($row['iaf_code'] ?? '') . ' ' . ($row['nace_code'] ?? '') . ' ' . ($row['food_code'] ?? '') . ' ' . ($row['medical_code'] ?? ''))) ?></td>
                            <td><?= esc($row['scope'] ?? '') ?></td>
                            <td class="text-end">
                                <form method="post" action="<?= site_url('masters/clients/' . $client['id'] . '/standards/' . $row['id'] . '/delete') ?>" onsubmit="return confirm('Remove this standard?');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($clientStandards === []): ?><tr><td colspan="4" class="text-secondary">No standards linked</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-xl-6">
        <section class="panel h-100">
            <div class="panel-title">Sites</div>
            <form method="post" action="<?= site_url('masters/clients/' . $client['id'] . '/sites') ?>" class="row g-2 mb-3">
                <?= csrf_field() ?>
                <div class="col-md-6"><input name="site_name" class="form-control" placeholder="Site name" required maxlength="180"></div>
                <div class="col-md-3"><input name="city" class="form-control" placeholder="City" maxlength="120"></div>
                <div class="col-md-3"><input name="country" class="form-control" placeholder="Country" maxlength="120"></div>
                <div class="col-md-3"><input name="employee_count" type="number" min="0" class="form-control" placeholder="Employees"></div>
                <div class="col-md-9"><input name="processes" class="form-control" placeholder="Processes"></div>
                <div class="col-12"><textarea name="address" class="form-control" rows="2" placeholder="Address"></textarea></div>
                <input type="hidden" name="active" value="1">
                <div class="col-12"><button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-plus me-2"></i>Add site</button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Site</th><th>Location</th><th>Employees</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($sites as $site): ?>
                        <tr>
                            <td><?= esc($site['site_name']) ?></td>
                            <td><?= esc(trim(($site['city'] ?? '') . ' ' . ($site['country'] ?? ''))) ?></td>
                            <td><?= esc((string) ($site['employee_count'] ?? '')) ?></td>
                            <td class="text-end">
                                <form method="post" action="<?= site_url('masters/clients/' . $client['id'] . '/sites/' . $site['id'] . '/delete') ?>" onsubmit="return confirm('Remove this site?');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($sites === []): ?><tr><td colspan="4" class="text-secondary">No sites added</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-xl-6">
        <section class="panel h-100">
            <div class="panel-title">Processes</div>
            <form method="post" action="<?= site_url('masters/clients/' . $client['id'] . '/processes') ?>" class="row g-2 mb-3">
                <?= csrf_field() ?>
                <div class="col-md-5"><input name="process_name" class="form-control" placeholder="Process name" required maxlength="180"></div>
                <div class="col-md-7"><input name="description" class="form-control" placeholder="Description"></div>
                <div class="col-12"><button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-plus me-2"></i>Add process</button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Process</th><th>Description</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($processes as $process): ?>
                        <tr>
                            <td><?= esc($process['process_name']) ?></td>
                            <td><?= esc($process['description'] ?? '') ?></td>
                            <td class="text-end">
                                <form method="post" action="<?= site_url('masters/clients/' . $client['id'] . '/processes/' . $process['id'] . '/delete') ?>" onsubmit="return confirm('Remove this process?');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($processes === []): ?><tr><td colspan="3" class="text-secondary">No processes added</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-xl-6">
        <section class="panel h-100">
            <div class="panel-title">Evidence metadata</div>
            <form method="post" action="<?= site_url('masters/clients/' . $client['id'] . '/attachments') ?>" class="row g-2 mb-3">
                <?= csrf_field() ?>
                <div class="col-md-4"><input name="category" class="form-control" placeholder="Category" required maxlength="80"></div>
                <div class="col-md-4"><input name="original_filename" class="form-control" placeholder="Original filename" required maxlength="255"></div>
                <div class="col-md-4"><input name="storage_path" class="form-control" placeholder="Storage path" required maxlength="500"></div>
                <div class="col-md-4"><input name="mime_type" class="form-control" placeholder="MIME type" maxlength="120"></div>
                <div class="col-md-4"><input name="file_size" type="number" min="0" class="form-control" placeholder="File size"></div>
                <div class="col-md-4"><input name="checksum_sha256" class="form-control" placeholder="SHA-256 checksum" maxlength="64"></div>
                <div class="col-12"><button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-plus me-2"></i>Add evidence</button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Category</th><th>File</th><th>Path</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($attachments as $attachment): ?>
                        <tr>
                            <td><?= esc($attachment['category']) ?></td>
                            <td><?= esc($attachment['original_filename']) ?></td>
                            <td><?= esc($attachment['storage_path']) ?></td>
                            <td class="text-end">
                                <form method="post" action="<?= site_url('masters/clients/' . $client['id'] . '/attachments/' . $attachment['id'] . '/delete') ?>" onsubmit="return confirm('Remove this evidence record?');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($attachments === []): ?><tr><td colspan="4" class="text-secondary">No evidence records added</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
