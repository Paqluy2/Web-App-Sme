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
        'id' => (int) $p['Product_id'],
        'price' => (float) $p['Retail_price']
    ];
}

$categoryFolderMap = [
    'Classic Drinks' => 'ClassicDrinks',
    'Classic Milk Tea' => 'ClassicMilkTea',
    'Premium Milk Tea' => 'PremMilkTea',
    'Premium Coffee' => 'PremCoffee',
    'Iced Blends' => 'IceBlend',
    'Fruit Tea' => 'FruitTea'
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
            <p style="color:var(--text-muted);">Choose from our 6 collections. Select size (16oz / 22oz) and add to
                cart.</p>
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
                        <img src="<?= $imageSrc ?>" alt="<?= sanitize($name) ?>"
                            onerror="this.onerror=null; this.src='images/logocafe.jpg';">
                    </div>
                    <div class="product-info">
                        <span class="product-category-tag"><?= sanitize($cat) ?></span>
                        <h3 class="product-name"><?= sanitize($name) ?></h3>

                        <!-- Size Selector -->
                        <div style="display:flex; gap:6px; margin-bottom:12px;">
                            <button type="button" class="pill-btn size-opt active"
                                style="padding:4px 10px; font-size:0.75rem;"
                                onclick="selectDrinkSize('<?= $hash ?>', <?= (int) $def16['id'] ?>, <?= (float) $def16['price'] ?>, '16oz', this)">
                                16oz
                            </button>
                            <?php if ($has22): ?>
                                <button type="button" class="pill-btn size-opt" style="padding:4px 10px; font-size:0.75rem;"
                                    onclick="selectDrinkSize('<?= $hash ?>', <?= (int) $data['sizes']['22oz']['id'] ?>, <?= (float) $data['sizes']['22oz']['price'] ?>, '22oz', this)">
                                    22oz
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="product-bottom">
                            <span class="product-price"
                                id="price-<?= $hash ?>">₱<?= number_format($def16['price'], 2) ?></span>
                            <button type="button" id="btn-<?= $hash ?>" class="btn btn-sm btn-primary btn-add-cart"
                                data-id="<?= $def16['id'] ?>" data-base-name="<?= sanitize($name) ?>"
                                data-name="<?= sanitize($name) ?> (16oz)" data-size="16oz"
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
            <button type="button" class="btn-del" id="closeCartBtn"
                style="font-size:1.8rem; background:none; border:none; cursor:pointer;">&times;</button>
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
                <button type="button" id="btnProceedCheckout" class="btn btn-primary btn-block btn-lg"
                    style="margin-top:12px;">Place Online Order</button>
            <?php else: ?>
                <button type="button" onclick="openAuthModal('Please sign in or create an account to finalize your order.')"
                    class="btn btn-primary btn-block btn-lg" style="margin-top:12px;">Sign In to Checkout</button>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Customer Login & Direct Registration Modal -->
    <div class="modal-overlay" id="authModal">
        <div class="modal-content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 id="authModalTitle">Welcome to Gigs Cafe</h3>
                <button type="button" onclick="closeAuthModal()"
                    style="font-size:1.5rem; background:none; border:none; cursor:pointer;">&times;</button>
            </div>

            <p id="authNotice" style="font-size:0.85rem; color:#b45309; margin-bottom:12px;"></p>

            <!-- 1. Customer Sign In Form -->
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

            <!-- 2. Direct Registration Form (No OTP) -->
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
    <script>
        function selectDrinkSize(hash, productId, price, size, btn) {
            // 1. Locate the specific card
            const card = document.getElementById('card-' + hash);
            if (card) {
                // Toggle the visual active state
                card.querySelectorAll('.size-opt').forEach(b => b.classList.remove('active'));
                if (btn) btn.classList.add('active');

                // Update the card price display
                const priceDisplay = card.querySelector('.price, .product-price, strong');
                if (priceDisplay) {
                    priceDisplay.textContent = '₱' + Number(price).toFixed(2);
                }
            }

            // 2. Update the target Add-to-Cart button
            const addBtn = document.getElementById('btn-' + hash);
            if (addBtn) {
                addBtn.setAttribute('data-id', productId);
                addBtn.setAttribute('data-price', price);
                addBtn.setAttribute('data-size', size);

                // Update the full item name sent to the cart drawer
                const baseName = addBtn.getAttribute('data-base-name') || addBtn.getAttribute('data-name').replace(/\s*\(\d+oz\)/i, '').trim();
                addBtn.setAttribute('data-name', `${baseName} (${size})`);
            }
        }

        // 5. If your card uses a hidden input for product_id, update it
        const hiddenIdInput = card.querySelector('input[name="product_id"]');
        if (hiddenIdInput) {
            hiddenIdInput.value = productId;
        }
}
    </script>
    <script>
        // Attach directly to window to guarantee global availability for inline onclick attributes
        window.selectDrinkSize = function (hash, productId, price, size, btn) {
            // 1. Locate the product card
            const card = document.getElementById('card-' + hash);
            if (card) {
                // Toggle visual active state on the size buttons
                card.querySelectorAll('.size-opt').forEach(b => {
                    b.classList.remove('active');
                    b.style.backgroundColor = '';
                    b.style.color = '';
                });
                if (btn) {
                    btn.classList.add('active');
                }

                // Update card price display
                const priceDisplay = card.querySelector('.price, .product-price, strong, .card-price');
                if (priceDisplay) {
                    priceDisplay.textContent = '₱' + Number(price).toFixed(2);
                }
            }

            // 2. Update the target Add-to-Cart button for this item
            const addBtn = document.getElementById('btn-' + hash);
            if (addBtn) {
                addBtn.setAttribute('data-id', productId);
                addBtn.setAttribute('data-price', price);
                addBtn.setAttribute('data-size', size);

                // Update the full item name passed into the cart drawer
                const baseName = addBtn.getAttribute('data-base-name')
                    || addBtn.getAttribute('data-name').replace(/\s*\(\d+oz\)/i, '').trim();
                addBtn.setAttribute('data-name', `${baseName} (${size})`);
            }
        };
    </script>

    <script src="assets/js/customer.js?v=2"></script>
</body>

</html>