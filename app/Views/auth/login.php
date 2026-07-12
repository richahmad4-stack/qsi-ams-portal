<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?> | QSI AMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --qsi-blue: #0f5ea8;
            --qsi-blue-dark: #0a3765;
            --qsi-border: #d7e2ee;
            --qsi-bg: #eef5fb;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #eef5fb 0%, #ffffff 52%, #dcecf9 100%);
            color: #17202a;
            font-family: Arial, Helvetica, sans-serif;
        }

        .auth-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .auth-panel {
            width: min(100%, 420px);
            background: #ffffff;
            border: 1px solid var(--qsi-border);
            border-radius: 8px;
            box-shadow: 0 18px 42px rgba(15, 94, 168, 0.13);
            padding: 32px;
        }

        .brand-mark {
            width: 76px;
            height: auto;
            border-radius: 4px;
            background: #ffffff;
            border: 1px solid var(--qsi-border);
            padding: 4px;
        }

        .btn-primary {
            --bs-btn-bg: var(--qsi-blue);
            --bs-btn-border-color: var(--qsi-blue);
            --bs-btn-hover-bg: var(--qsi-blue-dark);
            --bs-btn-hover-border-color: var(--qsi-blue-dark);
        }

        .form-control:focus {
            border-color: var(--qsi-blue);
            box-shadow: 0 0 0 0.2rem rgba(15, 94, 168, 0.12);
        }
    </style>
</head>
<body>
<main class="auth-shell">
    <section class="auth-panel" aria-label="Sign in">
        <div class="d-flex align-items-center gap-3 mb-4">
            <img class="brand-mark" src="<?= base_url('assets/img/qsi-logo.png') ?>" alt="QSI Canada Cert">
            <div>
                <h1 class="h4 mb-1">QSI AMS</h1>
                <p class="text-secondary mb-0">Secure access</p>
            </div>
        </div>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger" role="alert"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success" role="alert"><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('login') ?>" autocomplete="on" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="tenant_code" class="form-label">Tenant code</label>
                <input id="tenant_code" name="tenant_code" class="form-control" value="<?= esc($tenantCode) ?>" required maxlength="40" autocomplete="organization">
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input id="email" name="email" type="email" class="form-control" value="<?= esc($email) ?>" required maxlength="190" autocomplete="username">
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input id="password" name="password" type="password" class="form-control" required autocomplete="current-password">
            </div>

            <button class="btn btn-primary w-100" type="submit">
                <i class="fa-solid fa-right-to-bracket me-2" aria-hidden="true"></i>
                Sign in
            </button>
            <div class="text-center mt-3">
                <a href="<?= site_url('forgot-password') ?>">Forgot password?</a>
            </div>
        </form>
    </section>
</main>
</body>
</html>
