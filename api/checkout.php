<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/inventory.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['items']) || empty($data['customer'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid checkout data']);
    exit;
}

$items = $data['items'];
$cust = $data['customer'];
$fulfillment = $data['fulfillment'];
$db = getDB();

// 1. Verify recipe materials
foreach ($items as $item) {
    if (!CafeInventory::canFulfillProduct((int) $item['product_id'], (int) $item['qty'])) {
        echo json_encode(['success' => false, 'message' => "An item in your cart is currently out of stock."]);
        exit;
    }
}

// 2. Commit Order
$db->beginTransaction();
$txnCode = 'WEB-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));

try {
    $deliveryFee = ($fulfillment['type'] === 'Delivery') ? (defined('FLAT_DELIVERY_FEE') ? FLAT_DELIVERY_FEE : 50.00) : 0.00;
// 1. Grab the customer's IP address
    $customer_ip = $_SERVER['REMOTE_ADDR']; // Default fallback
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    // Render often passes multiple IPs in a comma-separated list; the first one is the real customer
    $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $customer_ip = trim($ip_list[0]);
} elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $customer_ip = $_SERVER['HTTP_CLIENT_IP'];
}
    // 2. Insert into online_orders table with the new ip_address column
    $stmtOrder = $db->prepare('
        INSERT INTO online_orders (transaction_code, customer_name, customer_email, customer_phone, fulfillment_type, delivery_address, pickup_slot, delivery_fee, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmtOrder->execute([
        $txnCode,
        sanitize($cust['name']),
        sanitize($cust['email']),
        sanitize($cust['phone']),
        $fulfillment['type'],
        sanitize($fulfillment['address'] ?? ''),
        sanitize($fulfillment['slot'] ?? ''),
        $deliveryFee,
        $customer_ip // 3. Pass the IP address into the database
    ]);

    // Insert line items into salestb under system employee 999
    $stmtSale = $db->prepare('
        INSERT INTO salestb (transaction_code, employee_id, product_id, quantity_sold, payment_method, order_type, sold_date)
        VALUES (?, 999, ?, ?, "Online/COD", ?, NOW())
    ');

    foreach ($items as $item) {
        $stmtSale->execute([$txnCode, (int) $item['product_id'], (int) $item['qty'], $fulfillment['type']]);
    }

    $db->commit();
    echo json_encode(['success' => true, 'order_id' => $txnCode]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Failed to save order: ' . $e->getMessage()]);
}