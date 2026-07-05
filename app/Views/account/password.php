<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?> | QSI AMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5">
    <div class="mx-auto bg-white border rounded-2 shadow-sm p-4" style="max-width: 520px;">
        <h1 class="h4 mb-4">Change password</h1>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger" role="alert"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('account/password') ?>">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="current_password" class="form-label">Current password</label>
                <input id="current_password" name="current_password" type="password" class="form-control" required autocomplete="current-password">
            </div>

            <div class="mb-3">
                <label for="new_password" class="form-label">New password</label>
                <input id="new_password" name="new_password" type="password" class="form-control" required autocomplete="new-password">
            </div>

            <div class="mb-4">
                <label for="confirm_password" class="form-label">Confirm new password</label>
                <input id="confirm_password" name="confirm_password" type="password" class="form-control" required autocomplete="new-password">
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-key me-2" aria-hidden="true"></i>
                    Update
                </button>
                <a class="btn btn-outline-secondary" href="<?= site_url('dashboard') ?>">Cancel</a>
            </div>
        </form>
    </div>
</main>
</body>
</html>
