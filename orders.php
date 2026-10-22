<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isCustomerLoggedIn = isset($_SESSION['customer_id']);
$orders = [];

if ($isCustomerLoggedIn) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM online_orders WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->execute([(int) $_SESSION['customer_id']]);
    $orders = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gigs Cafe - Order Tracking</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/customer.css">
</head>

<body>

    <!-- Navigation Bar -->
    <header class="top-nav-bar">
        <div class="nav-container">
            <a href="index.php" class="nav-brand">
                <img src="images/logocafe.jpg" alt="Logo" onerror="this.style.display='none'">
                <span class="brand-text">GigsCafe</span>
            </a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="menu.php">Menu</a></li>
                <li><a href="orders.php" class="active">Orders</a></li>
                <li><a href="account.php">Account</a></li>
            </ul>
            <div class="nav-actions">
                <a href="menu.php" class="cart-btn" style="text-decoration:none;">🛒 Menu</a>
                <?php if ($isCustomerLoggedIn): ?>
                    <a href="customer_auth.php?action=logout" class="btn btn-sm btn-secondary">Sign Out</a>
                <?php else: ?>
                    <button type="button" class="btn btn-sm btn-primary" onclick="openAuthModal()">Sign In</button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="content-section" style="max-width:900px;">
        <h1 style="color:var(--cafe-dark); margin-bottom:8px;">Live Order Tracking</h1>
        <p style="color:var(--text-muted); margin-bottom:24px;">Orders can be cancelled while in
            <strong>Pending</strong> or <strong>Preparing</strong> status. Once marked <strong>In Delivery</strong>,
            orders are locked.</p>

        <?php if (!$isCustomerLoggedIn): ?>
            <div class="card-box" style="text-align:center; padding:40px;">
                <h3>Please Sign In</h3>
                <p style="color:var(--text-muted); margin:10px 0 20px;">You need an account to view and track your drink
                    orders.</p>
                <button type="button" class="btn btn-primary" onclick="openAuthModal('Sign in to view your orders.')">Sign
                    In to Continue</button>
            </div>
        <?php elseif (empty($orders)): ?>
            <div class="card-box" style="text-align:center; padding:40px;">
                <h3>No Orders Found</h3>
                <p style="color:var(--text-muted); margin:10px 0 20px;">You haven't placed any orders yet.</p>
                <a href="menu.php" class="btn btn-primary">Browse Drink Menu</a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order):
                $status = $order['order_status'];
                $canCancel = ($status === 'Pending' || $status === 'Preparing');
                $badgeClass = 'badge-' . strtolower(str_replace(' ', '', $status));
                ?>
                <div class="order-tracking-card">
                    <div
                        style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
                        <div>
                            <h3 style="margin:0;">Order #<?= sanitize($order['transaction_code']) ?></h3>
                            <small
                                style="color:var(--text-muted);"><?= date('F j, Y - g:i A', strtotime($order['created_at'])) ?></small>
                        </div>
                        <div>
                            <span class="order-badge <?= $badgeClass ?>"><?= $status ?></span>
                        </div>
                    </div>

                    <div style="font-size:0.95rem; margin-bottom:14px; line-height:1.6;">
                        <div><strong>Fulfillment:</strong> <?= $order['fulfillment_type'] ?></div>
                        <?php if ($order['fulfillment_type'] === 'Delivery'): ?>
                            <div><strong>Address:</strong> <?= sanitize($order['delivery_address'] ?? 'N/A') ?></div>
                        <?php endif; ?>
                        <div><strong>Total Amount:</strong> ₱<?= number_format((float) $order['grand_total'], 2) ?></div>
                    </div>

                    <div
                        style="border-top:1px solid var(--cafe-border); padding-top:12px; display:flex; justify-content:flex-end;">
                        <?php if ($canCancel): ?>
                            <button type="button" class="btn btn-sm"
                                style="background:#fee2e2; color:#991b1b; border:1px solid #f87171;"
                                onclick="cancelOrder(<?= (int) $order['order_id'] ?>)">Cancel Order</button>
                        <?php elseif ($status === 'In Delivery'): ?>
                            <span style="font-size:0.85rem; color:#6b21a8; font-weight:700;">🚚 Out for delivery (Cannot be
                                cancelled)</span>
                        <?php elseif ($status === 'Cancelled'): ?>
                            <span style="font-size:0.85rem; color:#991b1b;">Order was cancelled</span>
                        <?php else: ?>
                            <span style="font-size:0.85rem; color:#065f46; font-weight:700;">✓ Completed</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <!-- Customer Login & Direct Registration Modal -->
    <div class="modal-overlay" id="authModal">
        <div class="modal-content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 id="authModalTitle">Welcome to Gigs Cafe</h3>
                <button type="button" onclick="closeAuthModal()"
                    style="font-size:1.5rem; background:none; border:none; cursor:pointer;">&times;</button>
            </div>

            <p id="authNotice" style="font-size:0.85rem; color:#b45309; margin-bottom:12px;"></p>

            <!-- 1. Sign In Form -->
            <form id="customerLoginForm" method="POST" action="customer_auth.php?action=login">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" required placeholder="juan@gmail.com">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
                <p style="text-align:center; margin-top:12px; font-size:0.85rem;">
                    New customer? <a href="javascript:void(0)" onclick="showRegisterForm()">Create an Account</a>
                </p>
            </form>

            <!-- 2. Direct Registration Form -->
            <form id="customerRegisterForm" style="display:none;" method="POST"
                action="customer_auth.php?action=register">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" class="form-control" required placeholder="Juan Dela Cruz">
                </div>
                <div class="form-group">
                    <label>Mobile Number</label>
                    <input type="tel" name="phone" class="form-control" required placeholder="09183477631"
                        maxlength="11">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" required placeholder="juan@gmail.com">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6"
                        placeholder="Min. 6 characters">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Create Account</button>
                <p style="text-align:center; margin-top:12px; font-size:0.85rem;">
                    Already have an account? <a href="javascript:void(0)" onclick="showLoginForm()">Sign In</a>
                </p>
            </form>
        </div>
    </div>

    <script src="assets/js/customer.js?v=2"></script>
    <script>
        async function cancelOrder(orderId) {
            if (!confirm('Are you sure you want to cancel this order?')) return;
            try {
                const res = await fetch(`api/customer_api.php?action=cancel_order&order_id=${orderId}`, { method: 'POST' });
                const data = await res.json();
                if (data.success) {
                    alert('Order cancelled successfully.');
                    window.location.reload();
                } else {
                    alert(data.message);
                }
            } catch (e) {
                alert('Could not cancel order due to network error.');
            }
        }
    </script>

</body>

</html>