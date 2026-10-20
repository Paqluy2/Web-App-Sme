<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
$db = getDB();

$action = $_GET['action'] ?? '';

// Helper: Standardize Philippine mobile numbers
function cleanPhoneNumber(string $phone): string
{
    $p = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($p, '639') && strlen($p) === 12) {
        $p = '0' . substr($p, 2);
    }
    return $p;
}

// -------------------------------------------------------------
// ACTION: DIRECT REGISTRATION (NO OTP)
// -------------------------------------------------------------
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = cleanPhoneNumber((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (empty($fullName) || empty($email) || empty($phone) || strlen($password) < 6) {
        header('Location: index.php?auth_error=' . urlencode('Please fill in all fields (password min. 6 characters).'));
        exit;
    }

    // Check duplicate email
    $checkEmail = $db->prepare("SELECT customer_id FROM customers WHERE email = ? LIMIT 1");
    $checkEmail->execute([$email]);
    if ($checkEmail->fetch()) {
        header('Location: index.php?auth_error=' . urlencode('An account with this email already exists. Please Sign In.'));
        exit;
    }

    // Check duplicate phone
    $checkPhone = $db->prepare("SELECT customer_id FROM customers WHERE phone = ? LIMIT 1");
    $checkPhone->execute([$phone]);
    if ($checkPhone->fetch()) {
        header('Location: index.php?auth_error=' . urlencode('This mobile number is already registered. Please Sign In.'));
        exit;
    }

    // Insert new customer directly
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $ins = $db->prepare("
        INSERT INTO customers (full_name, email, phone, password, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $ins->execute([$fullName, $email, $phone, $hashedPassword]);
    $newId = (int) $db->lastInsertId();

    // Auto-login session setup
    session_regenerate_id(true);
    $_SESSION['customer_id'] = $newId;
    $_SESSION['customer_name'] = $fullName;
    $_SESSION['customer_email'] = $email;

    // Redirect straight to account page to manage address details
    header('Location: account.php?registered=1');
    exit;
}

// -------------------------------------------------------------
// ACTION: LOGIN
// -------------------------------------------------------------
if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $_SESSION['auth_error'] = 'Please fill in both email and password.';
        header('Location: account.php?tab=login');
        exit;
    }

    $stmt = $db->prepare("SELECT customer_id, full_name, email, password FROM customers WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify hashed password
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['customer_id'] = (int) $user['customer_id'];
        $_SESSION['customer_name'] = $user['full_name'];
        $_SESSION['customer_email'] = $user['email'];
        unset($_SESSION['auth_error']);

        header('Location: orders.php');
        exit;
    } else {
        // Sets error indicator for wrong email or password
        $_SESSION['auth_error'] = 'Invalid email or password. Please try again.';
        header('Location: account.php?tab=login');
        exit;
    }
}