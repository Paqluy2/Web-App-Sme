<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// If already logged in, redirect straight to POS terminal
if (Auth::check()) {
    header('Location: pos.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Security session validation failure (CSRF).';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (Auth::login($username, $password)) {
            header('Location: pos.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gigs Cafe - Staff Login</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-body">
    <div class="auth-card">
        <h2>Gigs Cafe Gateway</h2>
        <p class="subtitle">Internal POS & Management Portal</p>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
            
            <div class="form-group">
                <label for="username">Staff Username</label>
                <input type="text" id="username" name="username" class="form-control" required autocomplete="username" placeholder="e.g. admin or paqluy">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </form>
    </div>
</body>
</html>