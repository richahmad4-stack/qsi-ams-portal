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
    <div class="mx-auto bg-white border rounded-2 shadow-sm p-4" style="max-width: 460px;">
        <h1 class="h4 mb-2">Reset password</h1>
        <p class="text-secondary">Enter your tenant code and email. If the account exists, we will send a reset link.</p>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger" role="alert"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('forgot-password') ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="tenant_code">Tenant code</label>
                <input class="form-control" id="tenant_code" name="tenant_code" value="<?= esc($tenantCode) ?>" required autocomplete="organization">
            </div>
            <div class="mb-4">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" type="email" name="email" value="<?= esc($email) ?>" required autocomplete="username">
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <a class="btn btn-outline-secondary" href="<?= site_url('login') ?>">Back to login</a>
                <button class="btn btn-primary" type="submit">Send reset link</button>
            </div>
        </form>
    </div>
</main>
</body>
</html>
