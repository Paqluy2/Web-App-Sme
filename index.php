<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

$isCustomerLoggedIn = isset($_SESSION['customer_id']);
$customerName = $_SESSION['customer_name'] ?? 'Account';
$currentPage = 'index.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gigs Cafe - Artisan Drinks & Fresh Brews</title>
    <!-- Relative paths to load CSS cleanly from /WebApp/ -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/customer.css">
</head>

<body>

    <!-- Top Navigation Bar -->
    <header class="top-nav-bar">
        <div class="nav-container">
            <a href="index.php" class="nav-brand">
                <img src="images/logocafe.jpg" alt="Gigs Cafe Logo" onerror="this.style.display='none'">
                <span class="brand-text">GigsCafe</span>
            </a>

            <ul class="nav-links">
                <li><a href="index.php" class="active">Home</a></li>
                <li><a href="menu.php">Menu</a></li>
                <li><a href="orders.php">Orders</a></li>
                <li><a href="account.php">Account</a></li>
            </ul>

            <div class="nav-actions">
                <a href="menu.php" class="cart-btn" style="text-decoration:none;">
                    🛒 Cart (<span id="cartCountBadge">0</span>)
                </a>
                <?php if ($isCustomerLoggedIn): ?>
                    <a href="customer_auth.php?action=logout" class="btn btn-sm btn-secondary">Sign Out</a>
                <?php else: ?>
                    <button type="button" class="btn btn-sm btn-primary" onclick="openAuthModal()">Sign In</button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-banner">
        <h1>Artisan Crafted Brews & Teas</h1>
        <p>Discover our signature milk teas, slow-dripped espresso, iced blends, and refreshing fruit tea infusions.</p>
        <a href="menu.php" class="btn btn-primary btn-lg"
            style="background:var(--cafe-caramel); border:none; padding:14px 28px; border-radius:30px; text-decoration:none; display:inline-block;">
            View Our Drink Menu (6 Collections)
        </a>
    </section>

    <!-- About the Cafe & Story Article -->
    <div class="content-section">
        <div class="grid-2col">
            <div>
                <span
                    style="font-weight:700; color:var(--cafe-caramel); text-transform:uppercase; letter-spacing:1px;">Our
                    Story</span>
                <h2 style="font-size:2.2rem; color:var(--cafe-dark); margin:8px 0 20px;">Passion Behind Every Pour</h2>
                <p style="line-height:1.8; color:var(--text-muted); margin-bottom:16px;">
                    Founded as a neighborhood retreat for students and coffee lovers, Gigs Cafe delivers balanced
                    flavors with every cup. From authentic Assam black tea bases to cold-extracted fruit concentrates
                    and espresso blends, our ingredients are portioned meticulously to maintain consistent quality.
                </p>
                <p style="line-height:1.8; color:var(--text-muted);">
                    Whether you need a quiet space to study or want chilled drinks delivered straight to your door, our
                    baristas prepare every ticket fresh with automated recipe precision.
                </p>
            </div>
            <div>
                <img src="images/wallLogo.jpg" alt="About Gigs Cafe"
                    style="width:100%; max-height:360px; object-fit:cover; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,0.12);"
                    onerror="this.style.display='none'">
            </div>
        </div>
    </div>

    <!-- News & Announcements -->
    <div class="content-section" style="margin-top:0;">
        <h2 style="text-align:center; color:var(--cafe-dark); margin-bottom:30px;">Cafe News & Daily Announcements</h2>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:24px;">
            <article class="card-box">
                <span style="font-size:0.8rem; font-weight:700; color:var(--cafe-caramel);">NEW ARRIVALS</span>
                <h3 style="margin:10px 0;">Matcha Lava & Bamboo Charcoal Brews</h3>
                <p style="color:var(--text-muted); line-height:1.6; font-size:0.95rem;">
                    Try our new layered specialty drinks combining Japanese culinary matcha and activated charcoal with
                    creamy dairy bases. Available in both 16oz and 22oz sizes.
                </p>
            </article>

            <article class="card-box">
                <span style="font-size:0.8rem; font-weight:700; color:var(--cafe-caramel);">SPECIAL PROMO</span>
                <h3 style="margin:10px 0;">LIMITED TIME OFFER</h3>
                <p style="color:var(--text-muted); line-height:1.6; font-size:0.95rem;">
                   Get a🍟FREE Great Fries🍟 (Classic)! No tricks, 𝗝𝗨𝗦𝗧 𝗧𝗥𝗘𝗔𝗧𝗦! 👻💙
For all that wants to hang out with drinks and free fries, what are you waiting for? 🤩🎉🥳
This Oct. 26 - Nov. 2, 2026, enjoy our freebie for every purchase of 3 Iced Blends (any flavor). 
FOR A LIMITED TIME ONLY! 🍟👻 Get ready, #GIGSFam!
                </p>
            </article>
        </div>
    </div>

    <!-- Footer -->
    <footer style="background: var(--cafe-espresso); color: #e2e8f0; padding: 40px 20px; margin-top: 60px;">
        <div
            style="max-width: 1200px; margin: 0 auto; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 30px;">
            <div>
                <h3 style="color:#fef08a; margin-bottom:10px;">GigsCafe</h3>
                <p style="max-width:320px; font-size:0.9rem; color:#cbd5e1;">Freshly brewed artisan coffee, signature
                    iced blends, milk teas, and fruit teas crafted daily.</p>
            </div>
            <div>
                <h4 style="margin-bottom:10px; color:#ffffff;">Quick Navigation</h4>
                <ul style="list-style:none; padding:0; font-size:0.85rem; line-height:1.8;">
                    <li><a href="menu.php" style="color:#cbd5e1; text-decoration:none;">Drink Menu</a></li>
                    <li><a href="orders.php" style="color:#cbd5e1; text-decoration:none;">Track Orders</a></li>
                    <li><a href="account.php" style="color:#cbd5e1; text-decoration:none;">Account Profile</a></li>
                </ul>
            </div>
            <div>
                <h4 style="margin-bottom:10px; color:#ffffff;">Store Hours</h4>
                <p style="font-size:0.85rem; color:#cbd5e1;">Monday – Sunday: 8:00 AM – 10:00 PM<br>Pickup & Local
                    Delivery Available.</p>
            </div>
        </div>
        <div
            style="text-align:center; border-top:1px solid #332722; margin-top:30px; padding-top:20px; font-size:0.8rem; color:#94a3b8;">
            &copy; <?= date('Y') ?> GigsCafe. All Rights Reserved.
        </div>
    </footer>

    <!-- Customer Login & Direct Registration Modal -->
    <div class="modal-overlay" id="authModal">
        <div class="modal-content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 id="authModalTitle">Welcome to GigsCafe</h3>
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

    <!-- 3. Registration Step 2: Enter SMS Code -->
    <form id="otpStep2Form" style="display:none;" onsubmit="handleVerifyOtp(event)">
        <div
            style="background:#e0f2fe; color:#0369a1; padding:12px; border-radius:6px; font-size:0.85rem; margin-bottom:14px;">
            Enter the 6-digit OTP sent to <strong id="displayTargetPhone"></strong>.
        </div>
        <div class="form-group">
            <label style="font-weight:700;">6-Digit SMS Verification Code</label>
            <input type="text" id="reg_otp" class="form-control" maxlength="6" pattern="[0-9]{6}" required
                placeholder="000000" style="font-size:1.4rem; letter-spacing:6px; text-align:center; font-weight:700;">
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

    <script src="assets/js/customer.js?v=2"></script>
</body>

</html>