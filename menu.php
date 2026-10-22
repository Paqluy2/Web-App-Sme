<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
if (!function_exists('sanitize')) {
    function sanitize(?string $data): string {
        return htmlspecialchars((string)($data ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
                <span class="brand-text">GigsCafe</span>
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
        <!-- JellyRadio Category Filter Bar -->
<div class="jelly-radio jelly-radio--categories" role="radiogroup" aria-label="Drink Categories" id="jelly-categories">
    <!-- "All Drinks" Chip (Index 0) -->
    <button 
        type="button" 
        role="radio" 
        aria-checked="true"
        class="jelly-radio__chip cat-btn active" 
        data-on="true"
        data-category="all"
        onclick="window.selectJellyCategory(0, 'all', this)">
        <span class="jelly-radio__skin">
            <span class="jelly-radio__label">All Drinks</span>
        </span>
    </button>

    <!-- Dynamic Category Chips (Index 1..N) -->
    <?php foreach ($categories as $idx => $catName): 
        $chipIndex = $idx + 1;
    ?>
        <button 
            type="button" 
            role="radio" 
            aria-checked="false"
            class="jelly-radio__chip cat-btn" 
            data-on="false"
            data-category="<?= htmlspecialchars($catName) ?>"
            onclick="window.selectJellyCategory(<?= $chipIndex ?>, '<?= htmlspecialchars($catName, ENT_QUOTES) ?>', this)">
            <span class="jelly-radio__skin">
                <span class="jelly-radio__label"><?= htmlspecialchars($catName) ?></span>
            </span>
        </button>
    <?php endforeach; ?>
</div>

        <!-- Products Grid -->
        <div class="product-grid">
            <?php foreach ($grouped as $name => $data):
                $cat = $data['type'];
                $folder = $categoryFolderMap[$cat] ?? 'ClassicDrinks';
                $imageSrc = "images/{$folder}/" . ($data['image'] ?? 'default.jpg');
                $has16 = isset($data['sizes']['16oz']);
                $has22 = isset($data['sizes']['22oz']);
                $defaultSize = $has16 ? '16oz' : array_key_first($data['sizes']);
                $defaultProduct = $data['sizes'][$defaultSize];
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
                        <div class="size-selector-wrap">
                            <?php if ($has16): ?>
                                <button type="button" class="pill-btn size-opt <?= $defaultSize === '16oz' ? 'active' : '' ?>"
                                    onclick="selectDrinkSize('<?= $hash ?>', <?= (int) $data['sizes']['16oz']['id'] ?>, <?= (float) $data['sizes']['16oz']['price'] ?>, '16oz', this)">
                                    16oz
                                </button>
                            <?php endif; ?>
                            <?php if ($has22): ?>
                                <button type="button" class="pill-btn size-opt <?= $defaultSize === '22oz' ? 'active' : '' ?>"
                                    onclick="selectDrinkSize('<?= $hash ?>', <?= (int) $data['sizes']['22oz']['id'] ?>, <?= (float) $data['sizes']['22oz']['price'] ?>, '22oz', this)">
                                    22oz
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="product-bottom">
                            <span class="product-price"
                                id="price-<?= $hash ?>">₱<?= number_format($defaultProduct['price'], 2) ?></span>
                            <button type="button" id="btn-<?= $hash ?>" class="btn btn-primary btn-add-cart"
                                data-id="<?= $defaultProduct['id'] ?>" data-base-name="<?= sanitize($name) ?>"
                                data-name="<?= sanitize($name) ?> (<?= $defaultSize ?>)" data-size="<?= $defaultSize ?>"
                                data-price="<?= $defaultProduct['price'] ?>">
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
    onclick="document.getElementById('cartDrawer').classList.remove('open'); document.getElementById('cartOverlay').style.display='none';"
    style="font-size:1.8rem; background:none; border:none; cursor:pointer; padding: 4px 10px;">&times;</button>
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
        // Attach directly to window to guarantee global availability for inline onclick attributes
        window.selectDrinkSize = function (hash, productId, price, size, btn) {
            // 1. Locate the product card
            const card = document.getElementById('card-' + hash);
            if (card) {
                // Toggle visual active state on the size buttons
                card.querySelectorAll('.size-opt').forEach(b => {
                    b.classList.remove('active');
                });
                if (btn) {
                    btn.classList.add('active');
                }

                // Update card price display
                const priceDisplay = card.querySelector('.product-price, .price, strong, .card-price');
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
                    || (addBtn.getAttribute('data-name') || '').replace(/\s*\(\d+oz\)/i, '').trim();
                addBtn.setAttribute('data-name', `${baseName} (${size})`);
            }
        };
    </script>
    <script>
(function() {
    // 1. Jelly Physics Config (For Category Bar Only)
    const JELLY_CFG = {
        swell: 0.2,
        barge: 6,
        shrink: 0.05,
        jelly: 1,
        bounce: 0.25,
        stagger: 22,
        stiffness: 580
    };

    const chipStates = new WeakMap();

    function getChipState(el) {
        if (!chipStates.has(el)) {
            chipStates.set(el, {
                x: { val: 0, vel: 0, target: 0 },
                sx: { val: 1, vel: 0, target: 1 },
                sy: { val: 1, vel: 0, target: 1 },
                raf: null
            });
        }
        return chipStates.get(el);
    }

    function stepSpring(axis, k, m, bounce, dt) {
        const damping = 2 * Math.sqrt(k * m) * (1 - bounce);
        const force = -k * (axis.val - axis.target) - damping * axis.vel;
        const accel = force / m;
        axis.vel += accel * dt;
        axis.val += axis.vel * dt;
        return Math.abs(axis.vel) > 0.001 || Math.abs(axis.target - axis.val) > 0.001;
    }

    function animateChip(el, targetX, targetS, far, instant) {
        const state = getChipState(el);
        state.x.target = targetX;
        state.sx.target = targetS;
        state.sy.target = targetS;

        if (instant) {
            state.x.val = targetX; state.x.vel = 0;
            state.sx.val = targetS; state.sx.vel = 0;
            state.sy.val = targetS; state.sy.vel = 0;
            el.style.transform = `translateX(${targetX.toFixed(2)}px) scale(${targetS}, ${targetS})`;
            return;
        }

        if (state.raf) cancelAnimationFrame(state.raf);

        const C = JELLY_CFG;
        const k = C.stiffness * (1 - 0.12 * Math.min(far, 3));
        const j = C.jelly;

        const kx = k * (1 + 0.24 * j);
        const mx = 0.9 - 0.1 * j;
        const bx = Math.min(0.85, C.bounce + 0.3 * j);

        const ky = k * (1 - 0.14 * j);
        const my = 0.9 + 0.05 * j;
        const by = C.bounce;

        let lastTime = performance.now();

        function tick(now) {
            const dt = Math.min((now - lastTime) / 1000, 0.032);
            lastTime = now;

            const movingX = stepSpring(state.x, k, 0.9, C.bounce, dt);
            const movingSX = stepSpring(state.sx, kx, mx, bx, dt);
            const movingSY = stepSpring(state.sy, ky, my, by, dt);

            el.style.transform = `translateX(${state.x.val.toFixed(2)}px) scale(${state.sx.val.toFixed(4)}, ${state.sy.val.toFixed(4)})`;

            if (movingX || movingSX || movingSY) {
                state.raf = requestAnimationFrame(tick);
            } else {
                el.style.transform = `translateX(${targetX.toFixed(2)}px) scale(${targetS}, ${targetS})`;
                state.raf = null;
            }
        }

        state.raf = requestAnimationFrame(tick);
    }

    function applyCategoryJelly(selectedIndex, instant = false) {
        const group = document.getElementById('jelly-categories');
        if (!group) return;
        const chips = Array.from(group.querySelectorAll('.jelly-radio__chip'));
        if (!chips.length) return;

        const C = JELLY_CFG;
        const selWidth = chips[selectedIndex]?.offsetWidth || 85;
        const push = (selWidth * C.swell) / 2 + C.barge;

        chips.forEach((chip, i) => {
            const isOn = i === selectedIndex;
            chip.setAttribute('data-on', isOn ? 'true' : 'false');
            chip.setAttribute('aria-checked', isOn ? 'true' : 'false');
            chip.classList.toggle('active', isOn);

            const far = Math.abs(i - selectedIndex);
            const dir = Math.sign(i - selectedIndex);
            const targetX = dir * push;
            const targetS = isOn ? 1 + C.swell : 1 - C.shrink;

            if (instant) {
                animateChip(chip, targetX, targetS, far, true);
            } else {
                setTimeout(() => {
                    animateChip(chip, targetX, targetS, far, false);
                }, far * C.stagger);
            }
        });
    }

    // 2. Category Click Handler (With Jelly Animation)
    window.selectJellyCategory = function(selectedIndex, category) {
        applyCategoryJelly(selectedIndex, false);

        const normalizedTarget = (category || 'all').trim().toLowerCase();
        document.querySelectorAll('.customer-product-card').forEach(card => {
            const cardCat = (card.getAttribute('data-category') || '').trim().toLowerCase();
            if (normalizedTarget === 'all' || normalizedTarget === 'all drinks' || cardCat === normalizedTarget) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    };

    // 3. 16oz / 22oz Size Click Handler (Original Static Toggle - No Jelly/Hover)
    window.selectDrinkSize = function(hash, productId, price, size, btn) {
        const card = document.getElementById('card-' + hash);
        if (card) {
            card.querySelectorAll('.size-opt').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');

            const priceEl = card.querySelector('.product-price, .price, strong');
            if (priceEl) {
                priceEl.textContent = '₱' + Number(price).toFixed(2);
            }
        }

        const addBtn = document.getElementById('btn-' + hash);
        if (addBtn) {
            addBtn.setAttribute('data-id', productId);
            addBtn.setAttribute('data-price', price);
            addBtn.setAttribute('data-size', size);
            const baseName = addBtn.getAttribute('data-base-name') 
                          || addBtn.getAttribute('data-name').replace(/\s*\(\d+oz\)/i, '').trim();
            addBtn.setAttribute('data-name', `${baseName} (${size})`);
        }
    };

    // Initialize Category Bar on load
    document.addEventListener('DOMContentLoaded', () => {
        applyCategoryJelly(0, true);
    });
})();
</script>

    <script src="assets/js/customer.js?v=2"></script>
</body>

</html>