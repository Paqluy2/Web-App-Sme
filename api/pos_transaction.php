<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/inventory.php';
require_once __DIR__ . '/../includes/xml_engine.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cart is empty or invalid']);
    exit;
}

$db = getDB();
$items = $input['items'];
$paymentMethod = sanitize($input['payment_method'] ?? 'Cash');
$amountTendered = (float) ($input['amount_tendered'] ?? 0.0);
$employeeId = $_SESSION['employee_id'];

// 1. Validate recipe stock availability
foreach ($items as $item) {
    $pId = (int) $item['product_id'];
    $qty = (int) $item['qty'];

    if (!CafeInventory::canFulfillProduct($pId, $qty)) {
        echo json_encode(['success' => false, 'message' => "Insufficient raw materials in inventory for product ID #{$pId}."]);
        exit;
    }
}

// 2. Process transaction inside a database transaction
$db->beginTransaction();
$transactionCode = 'POS-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));

try {
    $insertSale = $db->prepare('
        INSERT INTO salestb (transaction_code, employee_id, product_id, quantity_sold, payment_method, order_type, sold_date)
        VALUES (?, ?, ?, ?, ?, "POS", NOW())
    ');

    $grandTotal = 0.0;
    foreach ($items as $item) {
        $pId = (int) $item['product_id'];
        $qty = (int) $item['qty'];

        // Note: CalculateTotalAmount trigger automatically calculates total_amount
        // Note: AutoDeductRecipeStock trigger automatically updates the inventory table!
        $insertSale->execute([$transactionCode, $employeeId, $pId, $qty, $paymentMethod]);

        // Fetch the calculated line total from database
        $checkStmt = $db->prepare('SELECT Retail_price FROM menu_product WHERE Product_id = ?');
        $checkStmt->execute([$pId]);
        $price = (float) $checkStmt->fetchColumn();
        $grandTotal += ($price * $qty);
    }

    if ($paymentMethod === 'Cash' && $amountTendered < $grandTotal) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Insufficient cash tendered']);
        exit;
    }

    // Log the transaction in audit_log table
    $audit = $db->prepare('INSERT INTO audit_log (Employee_id, action_details, log_date) VALUES (?, ?, NOW())');
    $audit->execute([$employeeId, "Completed POS Transaction: {$transactionCode} (Total: {$grandTotal})"]);

    $db->commit();

    // 3. Generate XML Receipt
    $receiptXml = GigsXMLEngine::generateReceiptXML($transactionCode);

    echo json_encode([
        'success' => true,
        'transaction_code' => $transactionCode,
        'grand_total' => $grandTotal,
        'change' => ($paymentMethod === 'Cash') ? round($amountTendered - $grandTotal, 2) : 0.0,
        'xml_receipt' => $receiptXml
    ]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Processing failed: ' . $e->getMessage()]);
}