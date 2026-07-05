<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?> | QSI AMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; background: #f4f7fb; font-family: Arial, Helvetica, sans-serif; }
        .verify-shell { max-width: 760px; margin: 60px auto; padding: 0 18px; }
        .panel { background: #fff; border: 1px solid #dbe5ef; border-radius: 8px; padding: 28px; }
        .brand { color: #0f5ea8; font-weight: 700; }
    </style>
</head>
<body>
<main class="verify-shell">
    <section class="panel">
        <div class="brand mb-3">QSI AMS</div>
        <?php if ($certificate === null): ?>
            <h1 class="h4">Certificate not found</h1>
            <p class="text-secondary mb-0">No active certificate record matched this verification link.</p>
        <?php else: ?>
            <h1 class="h4">Certificate verified</h1>
            <table class="table table-sm mt-3">
                <tbody>
                <tr><th>Client</th><td><?= esc($certificate['company']) ?></td></tr>
                <tr><th>Certificate</th><td><?= esc($certificate['certificate_number']) ?></td></tr>
                <tr><th>Standard</th><td><?= esc($certificate['standard_code']) ?></td></tr>
                <tr><th>Scope</th><td><?= nl2br(esc($certificate['scope'])) ?></td></tr>
                <tr><th>Issue date</th><td><?= esc($certificate['issue_date']) ?></td></tr>
                <tr><th>Expiry date</th><td><?= esc($certificate['expiry_date']) ?></td></tr>
                <tr><th>Status</th><td><?= esc($certificate['status']) ?></td></tr>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
