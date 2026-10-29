<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
$db = getDB();

// Read action from either query string (?action=logout) or form input (<input name="action">)
$action = $_POST['action'] ?? $_GET['action'] ?? '';

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
// 1. ACTION: LOGOUT (Fixes the blank page)
// -------------------------------------------------------------
if ($action === 'logout') {
    // Clear customer session data
    unset(
        $_SESSION['customer_id'], 
        $_SESSION['customer_name'], 
        $_SESSION['customer_email'],
        $_SESSION['cart'],
        $_SESSION['auth_error']
    );
    session_write_close();
    header('Location: index.php');
    exit;
}

// -------------------------------------------------------------
// 2. ACTION: DIRECT REGISTRATION
// -------------------------------------------------------------
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitize($_POST['full_name'] ?? $_POST['name'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $phone    = cleanPhoneNumber((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (empty($fullName) || empty($email) || empty($phone) || strlen($password) < 6) {
        $_SESSION['auth_error'] = 'Please fill in all fields (password min. 6 characters).';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }

    // Check duplicate email
    $checkEmail = $db->prepare("SELECT customer_id FROM customers WHERE email = ? LIMIT 1");
    $checkEmail->execute([$email]);
    if ($checkEmail->fetch()) {
        $_SESSION['auth_error'] = 'An account with this email already exists. Please Sign In.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }

    // Check duplicate phone
    $checkPhone = $db->prepare("SELECT customer_id FROM customers WHERE phone = ? LIMIT 1");
    $checkPhone->execute([$phone]);
    if ($checkPhone->fetch()) {
        $_SESSION['auth_error'] = 'This mobile number is already registered. Please Sign In.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }

    // Insert new customer
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $ins = $db->prepare("
        INSERT INTO customers (full_name, email, phone, password, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $ins->execute([$fullName, $email, $phone, $hashedPassword]);
    $newId = (int) $db->lastInsertId();

    // Auto-login session setup
    session_regenerate_id(true);
    $_SESSION['customer_id']    = $newId;
    $_SESSION['customer_name']  = $fullName;
    $_SESSION['customer_email'] = $email;
    unset($_SESSION['auth_error']);

    session_write_close();
    header('Location: menu.php');
    exit;
}

// -------------------------------------------------------------
// 3. ACTION: LOGIN
// -------------------------------------------------------------
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    

    if ($email === '' || $password === '') {
        $_SESSION['auth_error'] = 'Please fill in both email and password.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'menu.php'));
        exit;
    }
    if ($customer['is_banned'] == 1) {
    echo json_encode(['success' => false, 'message' => 'This account has been suspended for violating our terms.']);
    exit;
}

    $stmt = $db->prepare("SELECT customer_id, full_name, email, password FROM customers WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify hashed or plain-text password
    if ($user && (password_verify($password, $user['password']) || $password === $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['customer_id']    = (int)$user['customer_id'];
        $_SESSION['customer_name']  = $user['full_name'] ?? 'Customer';
        $_SESSION['customer_email'] = $user['email'];
        unset($_SESSION['auth_error']);

        session_write_close();
        
        // Redirect back to where the customer was, or default to menu
        $redirectTo = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'menu.php';
        header("Location: {$redirectTo}");
        exit;
    } else {
        $_SESSION['auth_error'] = 'Invalid email or password. Please try again.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'menu.php'));
        exit;
    }
}

// Fallback if no valid action provided
header('Location: index.php');
exit;