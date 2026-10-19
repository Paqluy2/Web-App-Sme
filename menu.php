<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';

$isCustomerLoggedIn = isset($_SESSION['customer_id']);
$db = getDB();

// Pull all products grouped by category and name
$stmt = $db->query("SELECT * FROM menu_product ORDER BY Product_type, Product_name, size");
$allProducts = $stmt->fetchAll();

$grouped = [];
foreach ($allProducts as $p) {
    $name = $p['Product_name'];
    $grouped[$name]['type'] = $p['Product_type'];
    $grouped[$name]['image'] = $p['image'];
    $grouped[$name]['sizes'][$p['size']] = [
        'id'    => (int)$p['Product_id'],
        'price' => (float)$p['Retail_price']
    ];
}

$categoryFolderMap = [
    'Classic Drinks'   => 'ClassicDrinks',
    'Classic Milk Tea'  => 'ClassicMilkTea',
    'Premium Milk Tea'  => 'PremMilkTea',
    'Premium Coffee'    => 'PremCoffee',
    'Iced Blends'       => 'IceBlend',
    'Fruit Tea'         => 'FruitTea'
];
$categories = array_keys($categoryFolderMap);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gigs Cafe - Drink Menu</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/customer.css">
</head>
<body>

<!-- Navigation Bar -->
<header class="top-nav-bar">
    <div class="nav-container">
        <a href="index.php" class="nav-brand">
            <img src="images/logocafe.jpg" alt="Logo" onerror="this.style.display='none'">
            <span class="brand-text">☕ Gigs Cafe</span>
        </a>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="menu.php" class="active">Menu</a></li>
            <li><a href="orders.php">Orders</a></li>
            <li><a href="account.php">Account</a></li>
        </ul>
        <div class="nav-actions">
            <button type="button" class="cart-btn" id="openCartBtn">
                🛒 Cart (<span id="cartCountBadge">0</span>)
            </button>
            <?php if ($isCustomerLoggedIn): ?>
                <a href="customer_auth.php?action=logout" class="btn btn-sm btn-secondary">Sign Out</a>
            <?php else: ?>
                <button type="button" class="btn btn-sm btn-primary" onclick="openAuthModal()">Sign In</button>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Main Menu Catalog -->
<main class="content-section">
    <div style="text-align:center; margin-bottom:30px;">
        <h1 style="color:var(--cafe-dark); font-size:2.2rem;">Our Drink Menu</h1>
        <p style="color:var(--text-muted);">Choose from our 6 collections. Select size (16oz / 22oz) and add to cart.</p>
    </div>

    <!-- Category Pills -->
    <div class="category-nav">
        <button type="button" class="category-pill active" data-cat="all">All Drinks</button>
        <?php foreach ($categories as $cat): ?>
            <button type="button" class="category-pill" data-cat="<?= $cat ?>"><?= $cat ?></button>
        <?php endforeach; ?>
    </div>

    <!-- Products Grid -->
    <div class="product-grid">
        <?php foreach ($grouped as $name => $data): 
            $cat = $data['type'];
            $folder = $categoryFolderMap[$cat] ?? 'ClassicDrinks';
            $imageSrc = "images/{$folder}/" . ($data['image'] ?? 'default.jpg');
            $def16 = $data['sizes']['16oz'] ?? reset($data['sizes']);
            $has22 = isset($data['sizes']['22oz']);
            $hash = md5($name);
        ?>
        <div class="customer-product-card" data-category="<?= $cat ?>" id="card-<?= $hash ?>">
            <div class="product-img-wrap">
                <img src="<?= $imageSrc ?>" alt="<?= sanitize($name) ?>" onerror="this.onerror=null; this.src='images/logocafe.jpg';">
            </div>
            <div class="product-info">
                <span class="product-category-tag"><?= sanitize($cat) ?></span>
                <h3 class="product-name"><?= sanitize($name) ?></h3>
                
                <!-- Size Selector -->
                <div style="display:flex; gap:6px; margin-bottom:12px;">
                    <button type="button" class="pill-btn size-opt active" style="padding:4px 10px; font-size:0.75rem;" onclick="selectDrinkSize('<?= $hash ?>', <?= $def16['id'] ?>, <?= $def16['price'] ?>, this)">16oz</button>
                    <?php if ($has22): ?>
                    <button type="button" class="pill-btn size-opt" style="padding:4px 10px; font-size:0.75rem;" onclick="selectDrinkSize('<?= $hash ?>', <?= $data['sizes']['22oz']['id'] ?>, <?= $data['sizes']['22oz']['price'] ?>, this)">22oz</button>
                    <?php endif; ?>
                </div>

                <div class="product-bottom">
                    <span class="product-price" id="price-<?= $hash ?>">₱<?= number_format($def16['price'], 2) ?></span>
                    <button type="button" 
                            id="btn-<?= $hash ?>"
                            class="btn btn-sm btn-primary btn-add-cart"
                            data-id="<?= $def16['id'] ?>"
                            data-name="<?= sanitize($name) ?> (16oz)"
                            data-price="<?= $def16['price'] ?>">
                        + Add to Cart
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</main>

<!-- Slide-Out Cart Drawer -->
<div class="cart-drawer-overlay" id="cartOverlay"></div>
<aside class="cart-drawer" id="cartDrawer">
    <div class="cart-drawer-header">
        <h3>Your Cart</h3>
        <button type="button" class="btn-del" id="closeCartBtn" style="font-size:1.8rem; background:none; border:none; cursor:pointer;">&times;</button>
    </div>
    <div class="cart-drawer-body" id="cartItemsList"></div>
    <div class="cart-drawer-footer">
        <div class="form-group">
            <label style="font-weight:700;">Fulfillment Option</label>
            <div style="display:flex; gap:12px; margin-top:4px;">
                <label><input type="radio" name="cust_fulfillment" value="Pickup" checked> Pickup (Free)</label>
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

<!-- Auth Modal -->
<!-- Customer Login & Step-by-Step OTP Modal -->
<!-- Customer Login & Mobile SMS OTP Modal -->
<div class="modal-overlay" id="authModal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 id="authModalTitle">Welcome to Gigs Cafe</h3>
            <button type="button" onclick="closeAuthModal()" style="font-size:1.5rem; background:none; border:none; cursor:pointer;">&times;</button>
        </div>
        
        <p id="authNotice" style="font-size:0.85rem; color:var(--cafe-caramel); margin-bottom:12px;"></p>

        <!-- 1. Customer Sign In -->
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
                New to Gigs Cafe? <a href="javascript:void(0)" onclick="showRegisterStep1()">Register with Phone OTP</a>
            </p>
        </form>

        <!-- 2. Registration Step 1: Input Details -->
        <form id="otpStep1Form" style="display:none;" onsubmit="handleSendOtp(event)">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" id="reg_name" class="form-control" required placeholder="Juan Dela Cruz">
            </div>
            <div class="form-group">
                <label>Mobile Number (For OTP Verification)</label>
                <input type="tel" id="reg_phone" class="form-control" required placeholder="09183477631" maxlength="11">
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" id="reg_email" class="form-control" required placeholder="juan@gmail.com">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" id="reg_password" class="form-control" required minlength="6">
            </div>
            <button type="submit" id="btnSendOtp" class="btn btn-primary btn-block">Send Mobile OTP</button>
            <p style="text-align:center; margin-top:12px; font-size:0.85rem;">
                Already have an account? <a href="javascript:void(0)" onclick="showLoginForm()">Sign In</a>
            </p>
        </form>

        <!-- 3. Registration Step 2: Enter SMS Code -->
        <form id="otpStep2Form" style="display:none;" onsubmit="handleVerifyOtp(event)">
            <div style="background:#e0f2fe; color:#0369a1; padding:12px; border-radius:6px; font-size:0.85rem; margin-bottom:14px;">
                Enter the 6-digit OTP sent to <strong id="displayTargetPhone"></strong>.
            </div>
            <div class="form-group">
                <label style="font-weight:700;">6-Digit SMS Verification Code</label>
                <input type="text" id="reg_otp" class="form-control" maxlength="6" pattern="[0-9]{6}" required placeholder="000000" style="font-size:1.4rem; letter-spacing:6px; text-align:center; font-weight:700;">
            </div>
            <button type="submit" id="btnVerifyOtp" class="btn btn-primary btn-block">Verify Phone & Register</button>
            <div style="display:flex; justify-content:space-between; margin-top:12px; font-size:0.85rem;">
                <a href="javascript:void(0)" onclick="showRegisterStep1()">Change Number</a>
                <a href="javascript:void(0)" onclick="resendOtp()">Resend Code</a>
            </div>
        </form>
    </div>
</div>

        <!-- Register Form (Clean 4-Field Setup) -->
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
        <input type="tel" name="phone" class="form-control" required placeholder="09123456789">
    </div>
    <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" class="form-control" required minlength="6">
    </div>
    <button type="submit" class="btn btn-primary btn-block">Register Account</button>
    <p style="text-align:center; margin-top:10px; font-size:0.85rem;">
        Already have an account? <a href="#" onclick="toggleAuthForms(false)">Sign In</a>
    </p>
</form>
    </div>
</div>

<!-- Container for Firebase Phone Auth invisible reCAPTCHA -->
<div id="recaptcha-container"></div>

<!-- Firebase Compat SDKs (Browser-ready) -->
<script src="https://www.gstatic.com/firebasejs/10.9.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.9.0/firebase-auth-compat.js"></script>

<script>
// Your active Firebase project configuration
const firebaseConfig = {
  apiKey: "AIzaSyDREJi4hvvg9KQXTVJN61izQUYvJyvGWn8",
  authDomain: "sms-otp-84c35.firebaseapp.com",
  projectId: "sms-otp-84c35",
  storageBucket: "sms-otp-84c35.firebasestorage.app",
  messagingSenderId: "765733847392",
  appId: "1:765733847392:web:e89709b19bc3fe0ab8de05"
};

// Initialize Firebase
if (!firebase.apps.length) {
    firebase.initializeApp(firebaseConfig);
}
const auth = firebase.auth();
</script>

<script src="assets/js/customer.js"></script>
</body>
</html>