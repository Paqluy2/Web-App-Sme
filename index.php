<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$isCustomerLoggedIn = isset($_SESSION['customer_id']);
$customerName = $_SESSION['customer_name'] ?? 'Account';
$currentPage = 'index.php';
?>
<?php require_once 'config/firewall.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>GIGS CAFÉ</title>
    <!-- Relative paths to load CSS cleanly from /WebApp/ -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/customer.css?v=2.0">
</head>

<body>

    <!-- Top Navigation Bar -->
    <header class="top-nav-bar">
        <div class="nav-container">
            <a href="index.php" class="nav-brand">
                <img src="images/logocafe.jpg" alt="Gigs Cafe Logo" onerror="this.style.display='none'">
                <span class="brand-text">GIGS CAFÉ </span>
            </a>

            <ul class="nav-links">
                <li><a href="index.php" class="active">Home</a></li>
                <li><a href="menu.php">Menu</a></li>
                <li><a href="orders.php">Orders</a></li>
                <li><a href="account.php">Account</a></li>
            </ul>

            <div class="nav-actions">
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
            View Our Drink Menu
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

    <?php require_once __DIR__ . '/includes/customer_footer.php'; ?>

    <!-- Customer Login & Direct Registration Modal -->
    <div class="modal-overlay" id="authModal">
        <div class="modal-content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 id="authModalTitle">Welcome to GIGS CAFÉ</h3>
                <button type="button" onclick="closeAuthModal()"
                    style="font-size:1.5rem; background:none; border:none; cursor:pointer;">&times;</button>
            </div>

            <p id="authNotice" style="font-size:0.85rem; color:#b45309; margin-bottom:12px;"></p>

            <!-- 1. Customer Sign In Form -->
            <form id="customerLoginForm" method="POST" action="customer_auth.php?action=login">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" required placeholder="juan@gmail.com"
                        autocapitalize="none" autocorrect="off">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required autocapitalize="none"
                        autocorrect="off">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Sign In</button>

                <!-- Google Login Section Added Here -->
                <div
                    style="text-align: center; margin: 15px 0; color: var(--text-muted); font-size: 0.85rem; font-weight: bold;">
                    — OR —
                </div>

                <!-- The Google Button -->
                <div id="g_id_onload"
                    data-client_id="595592701169-rpfbal35qkmri3vu925okcj1a5ta1936.apps.googleusercontent.com"
                    data-context="signin" data-ux_mode="popup" data-callback="handleGoogleLogin"
                    data-auto_prompt="false">
                </div>
                <div class="g_id_signin" data-type="standard" data-size="large" data-theme="outline"
                    data-text="sign_in_with" data-shape="rectangular" data-logo_alignment="left" data-width="100%"
                    style="margin-bottom: 12px;"></div>

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
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script>
        function handleGoogleLogin(response) {
            fetch('api/google_auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: response.credential })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'index.php'; // Refresh to show logged-in state
                    } else {
                        alert('Google Login Failed: ' + data.message);
                    }
                })
                .catch(err => console.error("Error connecting to backend:", err));
        }
    </script>

    <script src="assets/js/customer.js?v=2"></script>
</body>

</html>