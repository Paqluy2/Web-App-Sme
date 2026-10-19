<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';

$isCustomerLoggedIn = isset($_SESSION['customer_id']);
$msg = '';
$err = '';
$user = null;

if ($isCustomerLoggedIn) {
    $db = getDB();
    $customerId = (int)$_SESSION['customer_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['form_action'] ?? '';

        if ($action === 'update_profile') {
            $fullName     = sanitize($_POST['full_name'] ?? '');
            $phone        = sanitize($_POST['phone'] ?? '');
            $label        = sanitize($_POST['address_label'] ?? 'Home');
            $houseUnit    = sanitize($_POST['house_unit'] ?? '');
            $street       = sanitize($_POST['street'] ?? '');
            $barangay     = sanitize($_POST['barangay'] ?? '');
            $city         = sanitize($_POST['city'] ?? '');
            $riderNotes   = sanitize($_POST['rider_notes'] ?? '');

            // Construct full address string for backward compatibility with orders
            $fullAddress = trim("{$houseUnit}, {$street}, {$barangay}, {$city}");

            $stmt = $db->prepare("
                UPDATE customers 
                SET full_name = ?, phone = ?, address_label = ?, house_unit = ?, street = ?, barangay = ?, city = ?, rider_notes = ?, address = ?
                WHERE customer_id = ?
            ");
            $stmt->execute([$fullName, $phone, $label, $houseUnit, $street, $barangay, $city, $riderNotes, $fullAddress, $customerId]);
            
            $_SESSION['customer_name'] = $fullName;
            $msg = 'Profile and delivery address updated successfully!';
        } elseif ($action === 'change_password') {
            $oldPass = $_POST['old_password'] ?? '';
            $newPass = $_POST['new_password'] ?? '';

            $stmt = $db->prepare("SELECT password FROM customers WHERE customer_id = ?");
            $stmt->execute([$customerId]);
            $currentHash = $stmt->fetchColumn();

            if (password_verify($oldPass, $currentHash)) {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $upd = $db->prepare("UPDATE customers SET password = ? WHERE customer_id = ?");
                $upd->execute([$newHash, $customerId]);
                $msg = 'Password changed successfully!';
            } else {
                $err = 'Current password entered is incorrect.';
            }
        }
    }

    $stmt = $db->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->execute([$customerId]);
    $user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gigs Cafe - Customer Account</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/customer.css">
    <style>
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .address-badge-group {
            display: flex;
            gap: 12px;
            margin-top: 6px;
            margin-bottom: 16px;
        }
        .address-radio {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            border: 1px solid var(--cafe-border);
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
        }
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<header class="top-nav-bar">
    <div class="nav-container">
        <a href="index.php" class="nav-brand">
            <img src="images/logocafe.jpg" alt="Logo" onerror="this.style.display='none'">
            <span class="brand-text">☕ Gigs Cafe</span>
        </a>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="menu.php">Menu</a></li>
            <li><a href="orders.php">Orders</a></li>
            <li><a href="account.php" class="active">Account</a></li>
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

<main class="content-section" style="max-width:800px;">
    <h1 style="color:var(--cafe-dark); margin-bottom:8px;">Customer Profile</h1>
    <p style="color:var(--text-muted); margin-bottom:24px;">Keep your contact details and delivery address accurate for fast rider dispatches.</p>

    <?php if ($msg): ?><div class="alert" style="background:#d1fae5; color:#065f46; margin-bottom:15px; padding:12px; border-radius:6px;"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger" style="margin-bottom:15px; padding:12px; border-radius:6px;"><?= $err ?></div><?php endif; ?>

    <?php if (!$isCustomerLoggedIn): ?>
        <div class="card-box" style="text-align:center; padding:40px;">
            <h3>Sign In Required</h3>
            <p style="color:var(--text-muted); margin:10px 0 20px;">Please sign in to access and update your delivery location.</p>
            <button type="button" class="btn btn-primary" onclick="openAuthModal('Sign in to view your account.')">Sign In to Account</button>
        </div>
    <?php else: ?>
        <div class="card-box" style="margin-bottom:24px;">
            <h3 style="margin-bottom:16px; color:var(--cafe-dark);">Contact & Precise Delivery Address</h3>
            <form method="POST">
                <input type="hidden" name="form_action" value="update_profile">

                <!-- Basic Info -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?= sanitize($user['full_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number (Required for Rider)</label>
                        <input type="tel" name="phone" class="form-control" value="<?= sanitize($user['phone']) ?>" required placeholder="09123456789">
                    </div>
                </div>

                <div class="form-group">
                    <label>Account Email</label>
                    <input type="email" class="form-control" value="<?= sanitize($user['email']) ?>" disabled style="background:#f1f5f9;">
                </div>

                <hr style="border:none; border-top:1px solid var(--cafe-border); margin:20px 0;">

                <!-- Address Label -->
                <label style="font-weight:700; font-size:0.9rem;">Address Type</label>
                <div class="address-badge-group">
                    <label class="address-radio">
                        <input type="radio" name="address_label" value="Home" <?= ($user['address_label'] ?? 'Home') === 'Home' ? 'checked' : '' ?>>
                        🏠 Home
                    </label>
                    <label class="address-radio">
                        <input type="radio" name="address_label" value="Work" <?= ($user['address_label'] ?? '') === 'Work' ? 'checked' : '' ?>>
                        🏢 Work / Office
                    </label>
                    <label class="address-radio">
                        <input type="radio" name="address_label" value="Other" <?= ($user['address_label'] ?? '') === 'Other' ? 'checked' : '' ?>>
                        📍 Other
                    </label>
                </div>

                <!-- Structured Address Fields -->
                <div class="form-row">
                    <div class="form-group">
                        <label>House / Unit / Block & Lot No.</label>
                        <input type="text" name="house_unit" class="form-control" required placeholder="e.g. Block 4 Lot 12, Unit 2B" value="<?= sanitize($user['house_unit'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Street / Subdivision</label>
                        <input type="text" name="street" class="form-control" required placeholder="e.g. Acacia St., Greenview Subd." value="<?= sanitize($user['street'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Barangay</label>
                        <input type="text" name="barangay" class="form-control" required placeholder="e.g. Brgy. San Jose" value="<?= sanitize($user['barangay'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>City / Municipality</label>
                        <input type="text" name="city" class="form-control" required placeholder="e.g. Rodriguez, Rizal" value="<?= sanitize($user['city'] ?? '') ?>">
                    </div>
                </div>

                <!-- Optional Rider Note / Landmark -->
                <div class="form-group">
                    <label>Landmark / Note for the Rider <span style="font-weight:normal; color:var(--text-muted);">(Optional)</span></label>
                    <input type="text" name="rider_notes" class="form-control" placeholder="e.g. Beside yellow sari-sari store, gate has black bell" value="<?= sanitize($user['rider_notes'] ?? '') ?>">
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top:10px;">Save Profile & Delivery Details</button>
            </form>
        </div>

        <!-- Security / Password Box -->
        <div class="card-box">
            <h3 style="margin-bottom:16px; color:var(--cafe-dark);">Change Password</h3>
            <form method="POST">
                <input type="hidden" name="form_action" value="change_password">
                <div class="form-row">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="old_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                </div>
                <button type="submit" class="btn btn-secondary">Update Password</button>
            </form>
        </div>
    <?php endif; ?>
</main>

<!-- Auth Modal -->
<div class="modal-overlay" id="authModal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 id="authModalTitle">Sign In</h3>
            <button type="button" onclick="closeAuthModal()" style="font-size:1.5rem; background:none; border:none; cursor:pointer;">&times;</button>
        </div>
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
        </form>
    </div>
</div>

<script src="assets/js/customer.js"></script>
</body>
</html>