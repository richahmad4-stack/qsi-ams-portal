<section class="panel mb-3">
    <div class="panel-title"><?= esc($title) ?></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
            <tr>
                <th>Audit</th>
                <th>Planned dates</th>
                <th>Status</th>
                <th class="text-end">Client file items</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($events as $event): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= esc($eventLabel((string) $event['event_type'])) ?></div>
                        <div class="small text-secondary"><?= esc($event['audit_number']) ?></div>
                    </td>
                    <td><?= esc(($event['planned_start_date'] ?? '') . ' to ' . ($event['planned_end_date'] ?? '')) ?></td>
                    <td><?= esc($event['status']) ?></td>
                    <td class="text-end">
                        <?php foreach (($event['client_documents'] ?? []) as $document): ?>
                            <?php if ($document['available']): ?>
                                <a class="btn btn-outline-danger btn-sm mb-1" href="<?= esc($document['url']) ?>"><?= esc($document['label']) ?></a>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary btn-sm mb-1" type="button" disabled title="<?= esc($document['status']) ?>"><?= esc($document['label']) ?></button>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($events === []): ?>
                <tr><td colspan="4" class="text-secondary">No records available yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
