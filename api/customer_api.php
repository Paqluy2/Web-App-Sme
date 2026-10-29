<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/db.php';
$action = $_GET['action'] ?? '';
$db = getDB();

// 1. Process Online Checkout
if ($action === 'checkout') {
    if (!isset($_SESSION['customer_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Please sign in to complete your checkout.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['items'])) {
        echo json_encode(['success' => false, 'message' => 'Your cart is empty.']);
        exit;
    }

    $customerId = (int) $_SESSION['customer_id'];
    $fulfillment = ($input['fulfillment_type'] === 'Delivery') ? 'Delivery' : 'Pickup';
    $items = $input['items'];

    // Retrieve customer profile & detailed address
    $custStmt = $db->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $custStmt->execute([$customerId]);
    $customer = $custStmt->fetch();

    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Customer profile not found.']);
        exit;
    }

    // Validate delivery address completeness
    $formattedAddress = 'Pickup In-Store';
    if ($fulfillment === 'Delivery') {
        if (empty($customer['house_unit']) || empty($customer['street']) || empty($customer['barangay']) || empty($customer['city'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Please complete your delivery address (House No., Street, Barangay, City) in your Account profile before placing a delivery order.'
            ]);
            exit;
        }

        // Build courier delivery string: "[Home] Blk 4 Lot 12, Acacia St, Brgy. San Jose, Rodriguez (Landmark: Near bakery)"
        $label = $customer['address_label'] ?? 'Home';
        $landmark = !empty($customer['rider_notes']) ? " (Note: {$customer['rider_notes']})" : '';
        $formattedAddress = "[{$label}] {$customer['house_unit']}, {$customer['street']}, {$customer['barangay']}, {$customer['city']}{$landmark}";
    }

    $db->beginTransaction();
    try {
        $subtotal = 0.0;
        foreach ($items as $item) {
            $pId = (int) $item['product_id'];
            $qty = (int) $item['qty'];

            $pStmt = $db->prepare("SELECT Retail_price FROM menu_product WHERE Product_id = ?");
            $pStmt->execute([$pId]);
            $price = (float) $pStmt->fetchColumn();
            
            // Fixed the syntax error here
            $subtotal += ($price * $qty);
        }

        // Define Delivery Fee constant if it's not defined globally, fallback to 50
        $feeAmount = defined('FLAT_DELIVERY_FEE') ? FLAT_DELIVERY_FEE : 50.00;
        $deliveryFee = ($fulfillment === 'Delivery') ? $feeAmount : 0.00;
        $grandTotal = $subtotal + $deliveryFee;
        $txnCode = 'ORD-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));

        // Retrieve customer name from session fallback to database record
        $customerName = $_SESSION['customer_name'] ?? $customer['full_name'] ?? 'Customer';
        $customerEmail = $_SESSION['customer_email'] ?? $customer['email'] ?? 'Unknown';
        $customerPhone = $_SESSION['customer_phone'] ?? $customer['phone'] ?? 'Unknown';

// 1. Grab the customer's IP address
$customer_ip = $_SERVER['REMOTE_ADDR']; // Default fallback
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    // Render often passes multiple IPs in a comma-separated list; the first one is the real customer
    $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $customer_ip = trim($ip_list[0]);
} elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $customer_ip = $_SERVER['HTTP_CLIENT_IP'];
}

// 2. Insert into online_orders with the ip_address column added
$orderStmt = $db->prepare("
    INSERT INTO online_orders (transaction_code, customer_id, customer_name, customer_email, customer_phone, fulfillment_type, delivery_address, subtotal, delivery_fee, grand_total, ip_address, order_status, created_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
");

// 3. Add $customer_ip to the execution array (right after $grandTotal)
$orderStmt->execute([$txnCode, $customerId, $customerName, $customerEmail, $customerPhone, $fulfillment, $formattedAddress, $subtotal, $deliveryFee, $grandTotal, $customer_ip]);

        // Insert line items into salestb under system employee 999
        $saleStmt = $db->prepare("
            INSERT INTO salestb (transaction_code, employee_id, product_id, quantity_sold, payment_method, order_type, sold_date)
            VALUES (?, 999, ?, ?, 'Online Order', ?, NOW())
        ");
        foreach ($items as $item) {
            $saleStmt->execute([$txnCode, (int) $item['product_id'], (int) $item['qty'], $fulfillment]);
        }

        $db->commit();
        echo json_encode(['success' => true, 'transaction_code' => $txnCode]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Order failed: ' . $e->getMessage()]);
    }
    exit;
}

// 2. Cancel Order
if ($action === 'cancel_order') {
    if (!isset($_SESSION['customer_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $orderId = (int) ($_GET['order_id'] ?? 0);
    $customerId = (int) $_SESSION['customer_id'];

    $stmt = $db->prepare("SELECT order_status FROM online_orders WHERE order_id = ? AND customer_id = ?");
    $stmt->execute([$orderId, $customerId]);
    $status = $stmt->fetchColumn();

    if (!$status) {
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }

    if ($status === 'In Delivery') {
        echo json_encode(['success' => false, 'message' => 'This order is already out for delivery and cannot be cancelled.']);
        exit;
    }

    if ($status === 'Completed' || $status === 'Cancelled') {
        echo json_encode(['success' => false, 'message' => 'This order can no longer be cancelled.']);
        exit;
    }

    $upd = $db->prepare("UPDATE online_orders SET order_status = 'Cancelled' WHERE order_id = ?");
    $upd->execute([$orderId]);

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);