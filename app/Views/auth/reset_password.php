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
    <div class="mx-auto bg-white border rounded-2 shadow-sm p-4" style="max-width: 520px;">
        <h1 class="h4 mb-2">Choose new password</h1>
        <p class="text-secondary">Use at least 12 characters with uppercase, lowercase, number, and symbol.</p>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger" role="alert"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('reset-password') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="selector" value="<?= esc($selector) ?>">
            <input type="hidden" name="token" value="<?= esc($token) ?>">

            <div class="mb-3">
                <label class="form-label" for="password">New password</label>
                <input class="form-control" id="password" type="password" name="password" required autocomplete="new-password">
            </div>
            <div class="mb-4">
                <label class="form-label" for="confirm_password">Confirm new password</label>
                <input class="form-control" id="confirm_password" type="password" name="confirm_password" required autocomplete="new-password">
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <a class="btn btn-outline-secondary" href="<?= site_url('login') ?>">Cancel</a>
                <button class="btn btn-primary" type="submit">Reset password</button>
            </div>
        </form>
    </div>
</main>
</body>
</html>
