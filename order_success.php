<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$id = sanitize($_GET['id'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Order Confirmation</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body class="auth-body">
    <div class="auth-card" style="max-width: 500px; text-align: center;">
        <h2 style="color: #27ae60;">Order Placed Successfully!</h2>
        <p>Thank you for shopping with us. Your transaction has been recorded.</p>
        <div class="alert alert-secondary" style="margin: 20px 0;">
            <strong>Reference ID:</strong> <code><?= $id ?></code>
        </div>
        <p>An XML manifest has been queued for warehouse staging and automated stock deduction.</p>
        <a href="/index.php" class="btn btn-primary" style="display:inline-block; margin-top:15px;">Return to
            Storefront</a>
    </div>
</body>

</html>