<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?> | QSI AMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5">
    <section class="mx-auto bg-white border rounded-2 shadow-sm p-4" style="max-width: 560px;">
        <h1 class="h4">Access denied</h1>
        <p class="text-secondary mb-4">Your account does not have permission for this page.</p>
        <a class="btn btn-primary" href="<?= site_url('dashboard') ?>">Dashboard</a>
    </section>
</main>
</body>
</html>
