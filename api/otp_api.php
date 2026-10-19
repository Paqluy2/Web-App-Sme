<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');

try {
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

// Helper: Standardize Philippine mobile numbers to 11 digits (e.g., 09183477631)
function cleanPhoneNumber(string $phone): string {
    $p = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($p, '639') && strlen($p) === 12) {
        $p = '0' . substr($p, 2);
    }
    return $p;
}

// -------------------------------------------------------------
// 1. ACTION: CHECK DUPLICATE PHONE / EMAIL (Pre-check)
// -------------------------------------------------------------
if ($action === 'check_availability') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = sanitize($input['email'] ?? '');
    $phone = cleanPhoneNumber((string)($input['phone'] ?? ''));

    // Check email
    $stmt = $db->prepare("SELECT customer_id FROM customers WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'This email is already registered. Please Sign In.']);
        exit;
    }

    // Check phone
    $stmt = $db->prepare("SELECT customer_id FROM customers WHERE phone = ? LIMIT 1");
    $stmt->execute([$phone]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'This mobile number is already registered. Please Sign In.']);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

// -------------------------------------------------------------
// 2. ACTION: COMPLETE FIREBASE REGISTRATION INTO MYSQL
// -------------------------------------------------------------
if ($action === 'complete_firebase_register') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        $fullName = sanitize($input['full_name'] ?? '');
        $email    = sanitize($input['email'] ?? '');
        $phone    = cleanPhoneNumber((string)($input['phone'] ?? ''));
        $password = (string)($input['password'] ?? '');

        if (empty($fullName) || empty($email) || empty($phone) || strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Please provide all required fields. Password must be at least 6 characters.']);
            exit;
        }

        // Final duplicate check
        $stmt = $db->prepare("SELECT customer_id FROM customers WHERE email = ? OR phone = ? LIMIT 1");
        $stmt->execute([$email, $phone]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'An account with this email or mobile number already exists.']);
            exit;
        }

        // Insert new verified customer record
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $ins = $db->prepare("
            INSERT INTO customers (full_name, email, phone, password, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $ins->execute([$fullName, $email, $phone, $hashedPassword]);
        $newCustomerId = (int)$db->lastInsertId();

        // Establish customer session
        session_regenerate_id(true);
        $_SESSION['customer_id'] = $newCustomerId;
        $_SESSION['customer_name'] = $fullName;
        $_SESSION['customer_email'] = $email;

        echo json_encode([
            'success'  => true,
            'message'  => 'Phone verified and account created!',
            'redirect' => 'account.php'
        ]);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid action parameter.']);