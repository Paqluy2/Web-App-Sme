<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');

// Turn off HTML error output so it never corrupts JSON
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function sendJsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    $json = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        http_response_code(500);
        echo '{"success":false,"message":"Unable to encode API response."}';
        exit;
    }
    echo $json;
    exit;
}

function readJsonInput(): array
{
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        throw new InvalidArgumentException('Invalid JSON request body.');
    }
    return $input;
}

try {
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();
} catch (Throwable $e) {
    sendJsonResponse(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()], 500);
}

$action = $_GET['action'] ?? '';

// Helper: Safely log audit events
function logAudit(PDO $db, string $actionText): void
{
    try {
        $stmt = $db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute(['audit_log']);
        if ($stmt->fetchColumn()) {
            $user = $_SESSION['admin_name'] ?? 'Admin';
            $ins = $db->prepare("INSERT INTO audit_log (`user`, `action`) VALUES (?, ?)");
            $ins->execute([$user, $actionText]);
        }
    } catch (Throwable $e) {
        // Ignore audit logging errors
    }
}

// -------------------------------------------------------------
// 1. FETCH PRODUCTS FOR POS
// -------------------------------------------------------------
if ($action === 'get_products') {
    try {
        $stmt = $db->prepare("SELECT * FROM menu_product");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $products = [];

        foreach ($rows as $r) {
            $keys = array_keys($r);
            $id = $r['id'] ?? $r['product_id'] ?? $r['prod_id'] ?? $r[$keys[0]] ?? 0;
            $name = $r['product_name'] ?? $r['name'] ?? $r['prod_name'] ?? $r['item_name'] ?? $r[$keys[1]] ?? 'Drink';
            $category = $r['category'] ?? $r['cat_name'] ?? 'Drinks';
            $price16 = (float) ($r['price_16oz'] ?? $r['price'] ?? $r['regular_price'] ?? 80);
            $price22 = (float) ($r['price_22oz'] ?? $r['large_price'] ?? ($price16 + 15));

            $products[] = [
                'id' => $id,
                'name' => (string) $name,
                'category' => (string) $category,
                'price_16oz' => $price16,
                'price_22oz' => $price22
            ];
        }
        sendJsonResponse(['success' => true, 'products' => $products]);
    } catch (Throwable $e) {
        sendJsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// -------------------------------------------------------------
// 2. PROCESS WALK-IN POS TRANSACTION & DEDUCT INVENTORY
// -------------------------------------------------------------
if ($action === 'process_pos') {
    try {
        $input = readJsonInput();
        $cart = $input['cart'] ?? [];
        $cash = filter_var($input['cash'] ?? 0, FILTER_VALIDATE_FLOAT);
        $method = $input['payment_method'] ?? 'Cash';

        if (!is_array($cart) || $cart === []) {
            sendJsonResponse(['success' => false, 'message' => 'Cart is empty.'], 400);
        }
        if ($cash === false || !is_finite((float) $cash) || (float) $cash < 0) {
            sendJsonResponse(['success' => false, 'message' => 'Invalid cash amount.'], 400);
        }
        if (!is_string($method)) {
            sendJsonResponse(['success' => false, 'message' => 'Invalid payment method.'], 400);
        }

        $total = 0.0;
        $totalQty = 0;
        $summaryParts = [];

        foreach ($cart as $item) {
            if (
                !is_array($item)
                || !isset($item['qty'], $item['price'], $item['name'], $item['size'])
                || !is_numeric($item['qty'])
                || !is_numeric($item['price'])
                || (int) $item['qty'] <= 0 || (float) $item['price'] < 0
            ) {
                sendJsonResponse(['success' => false, 'message' => 'Invalid item in cart.'], 400);
            }

            $qty = (int) $item['qty'];
            $price = (float) $item['price'];
            
            // FIXED: Proper multiplication operator
            $total += ($qty * $price); 
            $totalQty += $qty;
            $summaryParts[] = "{$item['name']} ({$item['size']}) x{$qty}";
        }

        if ($method === 'Cash' && (float) $cash < $total) {
            sendJsonResponse(['success' => false, 'message' => 'Insufficient cash tendered.'], 400);
        }

        $cash = (float) $cash;
        
        // FIXED: Proper subtraction operator
        $change = $method === 'Cash' ? ($cash - $total) : 0.0; 
        $orderNo = 'POS-' . date('Ymd-His') . '-' . rand(10, 99);
        $itemsSummary = implode(', ', $summaryParts);
        $cashier = $_SESSION['admin_name'] ?? 'Administrator';

        // 1. Resolve employee_id for constraint salestb_ibfk_1
        $employeeId = $_SESSION['employee_id'] ?? $_SESSION['admin_id'] ?? null;
        if (!$employeeId) {
            $empStmt = $db->query("SELECT employee_id FROM employees LIMIT 1");
            $empRow = $empStmt ? $empStmt->fetch(PDO::FETCH_ASSOC) : null;
            $employeeId = $empRow['employee_id'] ?? 1;
        }

        // 2. Fallback Product_id for constraint salestb_ibfk_2
        $defStmt = $db->query("SELECT Product_id FROM menu_product LIMIT 1");
        $defaultProductId = $defStmt ? (int) $defStmt->fetchColumn() : 1;

        $db->beginTransaction();

        // FIXED: Column names now exactly match your online orders and admin dashboard
        $insertSaleSql = "
            INSERT INTO salestb (employee_id, product_id, transaction_code, items, quantity_sold, total_amount, cash, change_amount, payment_method, order_type, cashier, sold_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'POS', ?, NOW())
        ";
        $stmt = $db->prepare($insertSaleSql);

        foreach ($cart as $item) {
            $itemQty = (int) $item['qty'];
            $itemPrice = (float) $item['price'];
            
            // FIXED: Proper multiplication operator
            $itemSubtotal = $itemQty * $itemPrice; 
            
            $itemDesc = "{$item['name']} ({$item['size']})";
            $prodId = !empty($item['product_id']) ? (int) $item['product_id'] : $defaultProductId;

            $stmt->execute([
                (int) $employeeId,
                $prodId,
                $orderNo,
                $itemDesc,
                $itemQty,
                $itemSubtotal,
                $cash,
                $change,
                $method,
                $cashier
            ]);
        }

        // Deduct inventory stock if matching material exists
        try {
            $inventoryUpdate = $db->prepare(
                "UPDATE inventory SET `current_stock` = GREATEST(0, `current_stock` - ?) WHERE `material_name` = ?"
            );
            foreach ($cart as $item) {
                $inventoryUpdate->execute([(int) $item['qty'], $item['name']]);
            }
        } catch (Throwable $invErr) {
            // Non-blocking
        }

        $db->commit();
        logAudit($db, "Processed POS Order $orderNo (₱" . number_format($total, 2) . ")");

        sendJsonResponse([
            'success' => true,
            'order_number' => $orderNo,
            'total' => $total,
            'cash' => $cash,
            'change' => $change,
            'method' => $method,
            'items' => $cart,
            'date' => date('M d, Y h:i A')
        ]);
    } catch (InvalidArgumentException $e) {
        sendJsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            try {
                $db->rollBack();
            } catch (Throwable $rb) {
            }
        }
        sendJsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

// -------------------------------------------------------------
// 3. INVENTORY MANAGEMENT (ADD, RESTOCK, UPDATE, DELETE)
// -------------------------------------------------------------
if ($action === 'save_inventory') {
    try {
        $input = readJsonInput();
        $id = filter_var($input['material_id'] ?? 0, FILTER_VALIDATE_INT);
        $nameValue = $input['material_name'] ?? null;
        $name = is_string($nameValue) ? trim($nameValue) : '';
        $rawStock = $input['current_stock'] ?? null;
        $unitValue = $input['unit'] ?? 'grams';
        $unit = is_string($unitValue) ? trim($unitValue) : '';

        if ($id === false || $id < 0) {
            sendJsonResponse(['success' => false, 'message' => 'Invalid material ID.'], 400);
        }
        if ($name === '') {
            sendJsonResponse(['success' => false, 'message' => 'Ingredient name cannot be empty.'], 400);
        }
        if (!is_numeric($rawStock) || !is_finite((float) $rawStock) || (float) $rawStock < 0) {
            sendJsonResponse(['success' => false, 'message' => 'Current stock must be a non-negative number.'], 400);
        }
        if ($unit === '') {
            sendJsonResponse(['success' => false, 'message' => 'Unit cannot be empty.'], 400);
        }
        $qty = (float) $rawStock;

        if ($id > 0) {
            $exists = $db->prepare('SELECT 1 FROM inventory WHERE `material_id` = ?');
            $exists->execute([$id]);
            if (!$exists->fetchColumn()) {
                sendJsonResponse(['success' => false, 'message' => 'Inventory item not found.'], 404);
            }
            $stmt = $db->prepare("UPDATE inventory SET `material_name` = ?, `current_stock` = ?, `unit` = ? WHERE `material_id` = ?");
            $stmt->execute([$name, $qty, $unit, $id]);
            logAudit($db, "Updated inventory material ID #$id:$name");
        } else {
            $stmt = $db->prepare("INSERT INTO inventory (`category`, `material_name`, `current_stock`, `unit`) VALUES (?, ?, ?, ?)");
            $stmt->execute(['Ingredients', $name, $qty, $unit]);
            logAudit($db, "Added new inventory material: $name");
        }

        sendJsonResponse(['success' => true]);
    } catch (InvalidArgumentException $e) {
        sendJsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    } catch (Throwable $e) {
        sendJsonResponse(['success' => false, 'message' => 'Inventory save failed.'], 500);
    }
}

if ($action === 'restock_inventory') {
    try {
        $input = readJsonInput();
        $id = filter_var($input['material_id'] ?? null, FILTER_VALIDATE_INT);
        $rawAddQty = $input['add_qty'] ?? null;

        if ($id === false || $id === null || $id <= 0) {
            sendJsonResponse(['success' => false, 'message' => 'Invalid material ID.'], 400);
        }
        if (!is_numeric($rawAddQty) || !is_finite((float) $rawAddQty) || (float) $rawAddQty <= 0) {
            sendJsonResponse(['success' => false, 'message' => 'Restock quantity must be a positive number.'], 400);
        }
        $addQty = (float) $rawAddQty;
        $exists = $db->prepare('SELECT 1 FROM inventory WHERE `material_id` = ?');
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) {
            sendJsonResponse(['success' => false, 'message' => 'Inventory item not found.'], 404);
        }
        $stmt = $db->prepare("UPDATE inventory SET `current_stock` = `current_stock` + ? WHERE `material_id` = ?");
        $stmt->execute([$addQty, $id]);
        logAudit($db, "Restocked material ID #$id by +$addQty");

        sendJsonResponse(['success' => true]);
    } catch (InvalidArgumentException $e) {
        sendJsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    } catch (Throwable $e) {
        sendJsonResponse(['success' => false, 'message' => 'Inventory restock failed.'], 500);
    }
}

if ($action === 'delete_inventory') {
    try {
        $input = readJsonInput();
        $id = filter_var($input['material_id'] ?? null, FILTER_VALIDATE_INT);

        if ($id === false || $id === null || $id <= 0) {
            sendJsonResponse(['success' => false, 'message' => 'Invalid material ID.'], 400);
        }

        $stmt = $db->prepare("DELETE FROM inventory WHERE `material_id` = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            sendJsonResponse(['success' => false, 'message' => 'Inventory item not found.'], 404);
        }
        logAudit($db, "Deleted inventory material ID #$id");

        sendJsonResponse(['success' => true]);
    } catch (InvalidArgumentException $e) {
        sendJsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    } catch (Throwable $e) {
        sendJsonResponse(['success' => false, 'message' => 'Inventory delete failed.'], 500);
    }
}

// -------------------------------------------------------------
// 4. UPDATE ONLINE ORDER STATUS
// -------------------------------------------------------------
if ($action === 'update_online_order') {
    try {
        $input = readJsonInput();
        $id = (int) ($input['id'] ?? 0);
        $status = trim((string) ($input['status'] ?? 'Pending'));

        if ($id <= 0) {
            sendJsonResponse(['success' => false, 'message' => 'Invalid Order ID.'], 400);
        }

        // Update order_status in online_orders table
        $stmt = $db->prepare("UPDATE online_orders SET `order_status` = ? WHERE `order_id` = ?");
        $stmt->execute([$status, $id]);

        logAudit($db, "Updated Online Order #$id status to$status");

        sendJsonResponse(['success' => true]);
    } catch (Throwable $e) {
        sendJsonResponse(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()], 500);
    }
}

sendJsonResponse(['success' => false, 'message' => 'Invalid API action endpoint.'], 400);