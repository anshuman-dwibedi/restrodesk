<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';

// Already logged in?
if (Auth::check() && Auth::role() === 'admin') {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    $v = Validator::make(['email' => $email, 'password' => $password], [
        'email'    => 'required|email',
        'password' => 'required|min:6',
    ]);

    if ($v->fails()) {
        $error = 'Please enter a valid email and password.';
    } else {
        $db    = Database::getInstance();
        $admin = $db->fetchOne('SELECT * FROM admins WHERE email = ?', [$email]);

        if ($admin && Auth::verifyPassword($password, $admin['password'])) {
            Auth::login($admin);
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Restrodesk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/devcore-suite/core/ui/devcore.css">
    <link rel="stylesheet" href="/devcore-suite/core/ui/parts/_icons.css">
    <style>
        :root { --dc-accent:#e8a838; --dc-accent-2:#f0c060; --dc-accent-glow:rgba(232,168,56,0.2); }
        body { display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .login-wrap { width:100%; max-width:420px; padding:0 24px; }
        .login-logo { text-align:center; margin-bottom:40px; }
        .login-logo h1 { font-family:var(--dc-font-display); font-size:1.6rem; font-weight:800; margin:12px 0 4px; letter-spacing:-0.02em; }
        .login-logo p  { color:var(--dc-text-3); font-size:0.9rem; }
        .login-badge   { display:inline-flex; align-items:center; gap:8px; background:var(--dc-accent-glow); border:1px solid rgba(232,168,56,0.3); border-radius:var(--dc-radius-full); padding:8px 20px; margin-bottom:4px; }
        .login-field   { margin-bottom:16px; }
        .login-field label { display:block; font-size:0.85rem; font-weight:600; margin-bottom:6px; color:var(--dc-text-2); }
        .login-error { background:rgba(255,92,106,0.1); border:1px solid rgba(255,92,106,0.25); color:var(--dc-danger); padding:12px 16px; border-radius:var(--dc-radius); font-size:0.875rem; margin-bottom:20px; }
        .btn-gold { background:var(--dc-accent)!important; border-color:var(--dc-accent)!important; color:#000!important; font-weight:700!important; }
        .btn-gold:hover { background:var(--dc-accent-2)!important; border-color:var(--dc-accent-2)!important; }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-logo">
        <div class="login-badge">
            <i class="dc-icon dc-icon-utensils dc-icon-sm" style="color:var(--dc-accent-2)"></i>
            <span style="font-weight:700;color:var(--dc-accent-2)">Restrodesk</span>
        </div>
        <h1>Admin Portal</h1>
        <p>Sign in to manage orders &amp; menu</p>
    </div>

    <div class="dc-card">
        <?php if ($error): ?>
            <div class="login-error"><i class="dc-icon dc-icon-alert-triangle dc-icon-sm"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="login-field">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="dc-input"
                       placeholder="admin@restaurant.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="login-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="dc-input"
                       placeholder="••••••••" required>
            </div>
            <button type="submit" class="dc-btn dc-btn-primary dc-btn-full btn-gold" style="margin-top:8px;">
                Sign In <i class="dc-icon dc-icon-arrow-right dc-icon-sm"></i>
            </button>
        </form>

        <p style="text-align:center; margin-top:20px; font-size:0.8rem; color:var(--dc-text-3);">
            Demo: admin@restaurant.com / admin123
        </p>
    </div>
</div>
<script src="/devcore-suite/core/ui/devcore.js"></script>
</body>
</html>
