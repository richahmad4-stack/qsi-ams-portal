<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="panel">
    <div class="panel-title">Document templates</div>
    <div class="table-responsive">
        <table class="table table-striped align-middle" data-table="true">
            <thead>
            <tr>
                <th>Name</th>
                <th>Key</th>
                <th>Type</th>
                <th>Version</th>
                <th>Status</th>
                <th class="text-end">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($templates as $template): ?>
                <tr>
                    <td><?= esc($template['name']) ?></td>
                    <td><?= esc($template['template_key']) ?></td>
                    <td><?= esc($template['document_type']) ?></td>
                    <td><?= esc($template['active_version'] ?? 'None') ?></td>
                    <td><?= esc($template['status']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-outline-primary btn-sm" href="<?= site_url('masters/templates/' . $template['id'] . '/edit') ?>">
                            <i class="fa-solid fa-pen-to-square me-1" aria-hidden="true"></i>
                            Edit
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?= $this->endSection() ?>
