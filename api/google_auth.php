<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=UTF-8');

// Adjust this path if your db.php is located elsewhere
require_once __DIR__ . '/../config/db.php'; 

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'No token provided.']);
    exit;
}

// 1. Verify the token directly with Google's servers
$verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . $token;
$response = file_get_contents($verifyUrl);
$userData = json_decode($response, true);

if (isset($userData['error'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid Google token.']);
    exit;
}

// 2. Security Check: Match your Client ID
$myClientId = '595592701169-rpfbal35qkmri3vu925okcj1a5ta1936.apps.googleusercontent.com';
if ($userData['aud'] !== $myClientId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized client ID.']);
    exit;
}

// 3. Extract the verified customer data
$googleId = $userData['sub'];
$email = $userData['email']; // No need to sanitize, Google guarantees format
$name = $userData['name'];
$db = getDB();

try {
    // 4. Check if this customer already exists in your table (using customer_id and full_name)
    $stmt = $db->prepare('SELECT customer_id, full_name FROM customers WHERE email = ?');
    $stmt->execute([$email]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($customer) {
        // Optional: If they previously registered locally, update them to use Google Auth going forward
        $update = $db->prepare("UPDATE customers SET oauth_provider = 'google', oauth_uid = ? WHERE email = ? AND (oauth_provider = 'local' OR oauth_provider IS NULL)");
        $update->execute([$googleId, $email]);

        // Log them in based on your table columns
        $_SESSION['customer_id'] = $customer['customer_id'];
        $_SESSION['customer_name'] = $customer['full_name'];
        $_SESSION['customer_email'] = $email;
    } else {
        // New Customer: Register them using exact column names from your database
        $insert = $db->prepare("
            INSERT INTO customers (full_name, email, phone, oauth_provider, oauth_uid, created_at) 
            VALUES (?, ?, ?, 'google', ?, NOW())
        ");
        
        // Pass 'N/A' as the default phone number so the database accepts it
        $insert->execute([$name, $email, 'N/A', $googleId]);
        
        // Log the brand new customer in
        $_SESSION['customer_id'] = $db->lastInsertId();
        $_SESSION['customer_name'] = $name;
        $_SESSION['customer_email'] = $email;
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}