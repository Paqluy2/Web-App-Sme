<?php
declare(strict_types=1);

// 1. Session start MUST follow declare statement immediately
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

$isCustomerLoggedIn = !empty($_SESSION['customer_id']);
$customerName       =$_SESSION['customer_name'] ?? 'Account';
$currentPage        = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gigs Cafe - Artisan Drinks & Fresh Brews</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/customer.css">
</head>

<body>

    <!-- Global Navigation Bar -->
    <header class="top-nav-bar">
        <div class="nav-container">
            <a href="index.php" class="nav-brand">
                <img src="images/logocafe.jpg" alt="Gigs Cafe" onerror="this.style.display='none'">
                <span class="brand-text">GigsCafe</span>
            </a>

            <ul class="nav-links">
                <li><a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">Home</a></li>
                <li><a href="menu.php" class="<?= $currentPage === 'menu.php' ? 'active' : '' ?>">Menu</a></li>
                <li><a href="orders.php" class="<?= $currentPage === 'orders.php' ? 'active' : '' ?>">Orders</a></li>
                <li><a href="account.php" class="<?= $currentPage === 'account.php' ? 'active' : '' ?>">Account</a></li>
            </ul>
<?php
// Calculate total quantity of items in cart from session
$totalCartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $totalCartCount += (int)($item['qty'] ?? $item['quantity'] ?? 1);
    }
}
?>
            <div class="nav-actions">
                <button type="button" class="cart-btn" id="openCartBtn">
                    🛒 Cart (<span id="cartCountBadge">0</span>)
                </button>
                <?php if ($isCustomerLoggedIn): ?>
                    <a href="account.php" class="nav-user-greeting">Hi, <?= htmlspecialchars($customerName) ?></a>
                    <a href="customer_auth.php?action=logout" class="btn btn-logout">Sign Out</a>
                <?php else: ?>
                    <!-- Fixed: Calls openAuthModal() so it correctly triggers the modal -->
                    <button type="button" class="btn btn-signin" onclick="typeof openAuthModal === 'function' ? openAuthModal() : openLoginModal()">Sign In</button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Slide-Out Shopping Cart Drawer -->
    <div class="cart-drawer-overlay" id="cartOverlay"></div>
    <aside class="cart-drawer" id="cartDrawer">
        <div class="cart-drawer-header">
            <h3>Your Selected Drinks</h3>
            <button type="button" class="btn-del" id="closeCartBtn" style="font-size:1.8rem;">&times;</button>
        </div>

        <div class="cart-drawer-body" id="cartItemsList">
            <!-- Rendered by assets/js/customer.js -->
        </div>

        <div class="cart-drawer-footer">
            <div class="form-group">
                <label style="font-weight:700;">Fulfillment Option</label>
                <div style="display:flex; gap:12px; margin-top:4px;">
                    <label><input type="radio" name="cust_fulfillment" value="Pickup" checked> Store Pickup (Free)</label>
                    <label><input type="radio" name="cust_fulfillment" value="Delivery"> Delivery (₱50.00)</label>
                </div>
            </div>

            <div class="cart-totals-breakdown">
                <div class="line"><span>Subtotal:</span><span id="drawerSubtotal">₱0.00</span></div>
                <div class="line"><span>Delivery Fee:</span><span id="drawerDelivery">₱0.00</span></div>
                <div class="line total"><span>Total:</span><span id="drawerTotal">₱0.00</span></div>
            </div>

            <?php if ($isCustomerLoggedIn): ?>
                <button type="button" id="btnProceedCheckout" class="btn btn-primary btn-block btn-lg" style="margin-top:12px;">Place Online Order</button>
            <?php else: ?>
                <button type="button" onclick="openAuthModal('Please sign in or create an account to finalize your order.')" class="btn btn-primary btn-block btn-lg" style="margin-top:12px;">Sign In to Checkout</button>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Customer Login / Registration Modal -->
    <div class="modal-overlay" id="authModal">
        <div class="modal-content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 id="authModalTitle">Welcome to Gigs Cafe</h3>
                <button type="button" class="btn-del" onclick="closeAuthModal()" style="font-size:1.5rem;">&times;</button>
            </div>

            <p id="authNotice" style="font-size:0.85rem; color:var(--cafe-caramel, #c084fc); margin-bottom:12px;"></p>

            <!-- Login Form -->
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
                <p style="text-align:center; margin-top:10px; font-size:0.85rem;">
                    New to Gigs Cafe? <a href="#" onclick="toggleAuthForms(true); return false;">Create an Account</a>
                </p>
            </form>

            <!-- Register Form -->
            <form id="customerRegisterForm" method="POST" action="customer_auth.php?action=register" style="display:none;">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" class="form-control" required placeholder="Juan Dela Cruz">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" required placeholder="juan@gmail.com">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" class="form-control" required placeholder="09123456