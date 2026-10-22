<?php
declare(strict_types=1);


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

/**
 * Dynamically exports all MySQL tables in `gigscafe` to the 7 XML files in /data/
 */
function syncAllXmlFiles(PDO $db): array
{
    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0777, true);
    }

    $results = [];

    // Helper: Safely query a table without throwing fatal errors if empty/missing
    $fetchTable = function (string $sql) use ($db): array {
        try {
            $stmt = $db->query($sql);
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            return [];
        }
    };

    // Helper: Convert array of associative rows into a formatted XML file
    $writeXml = function (string $filename, string $rootTag, string $itemTag, array $rows) use ($dataDir, &$results): void {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement($rootTag);
        $root->setAttribute('last_updated', date('Y-m-d H:i:s'));
        $dom->appendChild($root);

        foreach ($rows as $row) {
            $item = $dom->createElement($itemTag);
            foreach ($row as $key => $val) {
                // Sanitize XML tag name
                $cleanKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) $key);
                if (is_numeric(substr($cleanKey, 0, 1))) {
                    $cleanKey = 'col_' . $cleanKey;
                }
                // Do not expose raw password hashes in XML exports if avoidable, or keep safe string
                $child = $dom->createElement($cleanKey);
                $child->appendChild($dom->createTextNode((string) ($val ?? '')));
                $item->appendChild($child);
            }
            $root->appendChild($item);
        }

        $filePath = $dataDir . '/' . $filename;
        $dom->save($filePath);
        $results[$filename] = count($rows);
    };

    // ---------------------------------------------------------
    // 1. audit_logs.xml (From `audit_log`)
    // ---------------------------------------------------------
    $auditRows = $fetchTable("SELECT * FROM audit_log ORDER BY 1 DESC");
    $writeXml('audit_logs.xml', 'audit_logs', 'log', $auditRows);

    // ---------------------------------------------------------
    // 2. products.xml & categories.xml (From `menu_product`)
    // ---------------------------------------------------------
    $productRows = $fetchTable("SELECT * FROM menu_product ORDER BY 1 ASC");
    $writeXml('products.xml', 'products', 'product', $productRows);

    // Extract unique categories from `menu_product`
    $categoriesMap = [];
    $catId = 1;
    foreach ($productRows as $p) {
        $catName = trim((string) ($p['category'] ?? $p['cat_name'] ?? 'Drinks'));
        if ($catName !== '' && !isset($categoriesMap[$catName])) {
            $categoriesMap[$catName] = [
                'category_id' => $catId++,
                'category_name' => $catName,
                'product_count' => 0
            ];
        }
        if (isset($categoriesMap[$catName])) {
            $categoriesMap[$catName]['product_count']++;
        }
    }
    $writeXml('categories.xml', 'categories', 'category', array_values($categoriesMap));

    // ---------------------------------------------------------
    // 3. orders.xml (From `online_orders` / `orders`)
    // ---------------------------------------------------------
    $onlineOrders = $fetchTable("SELECT * FROM online_orders ORDER BY 1 DESC");
    if (empty($onlineOrders)) {
        $onlineOrders = $fetchTable("SELECT * FROM orders ORDER BY 1 DESC");
    }
    $writeXml('orders.xml', 'orders', 'order', $onlineOrders);

    // ---------------------------------------------------------
    // 4. restock_logs.xml (From `inventory`)
    // ---------------------------------------------------------
    $inventoryRows = $fetchTable("SELECT * FROM inventory ORDER BY 1 ASC");
    $writeXml('restock_logs.xml', 'restock_logs', 'stock_item', $inventoryRows);

    // ---------------------------------------------------------
    // 5. transactions.xml (From `salestb` Walk-in POS Sales)
    // ---------------------------------------------------------
    $salesRows = $fetchTable("SELECT * FROM salestb ORDER BY 1 DESC");
    $writeXml('transactions.xml', 'transactions', 'transaction', $salesRows);

    // ---------------------------------------------------------
    // 6. users.xml (Combines `employees` and `customers`)
    // ---------------------------------------------------------
    $usersCombined = [];
    $employees = $fetchTable("SELECT * FROM employees");
    foreach ($employees as $emp) {
        $keys = array_keys($emp);
        $usersCombined[] = [
            'user_type' => 'Employee / Admin',
            'id' => $emp['id'] ?? $emp['emp_id'] ?? $emp[$keys[0]] ?? '',
            'name' => $emp['full_name'] ?? $emp['name'] ?? $emp['username'] ?? 'Admin',
            'email_or_username' => $emp['username'] ?? $emp['email'] ?? '',
            'role' => $emp['role'] ?? $emp['position'] ?? 'Staff'
        ];
    }

    $customers = $fetchTable("SELECT * FROM customers");
    foreach ($customers as $cust) {
        $keys = array_keys($cust);
        $usersCombined[] = [
            'user_type' => 'Customer',
            'id' => $cust['customer_id'] ?? $cust['id'] ?? $cust[$keys[0]] ?? '',
            'name' => $cust['full_name'] ?? $cust['name'] ?? '',
            'email_or_username' => $cust['email'] ?? '',
            'phone' => $cust['phone'] ?? '',
            'role' => 'Customer'
        ];
    }
    $writeXml('users.xml', 'users', 'user', $usersCombined);

    return $results;
}

// If accessed directly via browser or AJAX, run the sync immediately
if (basename($_SERVER['PHP_SELF']) === 'export_xml.php') {
    try {
        $db = getDB();
        $summary = syncAllXmlFiles($db);

        if (isset($_GET['format']) && $_GET['format'] === 'json') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => true, 'message' => 'All 7 XML files updated!', 'counts' => $summary]);
            exit;
        }

        // Redirect back to POS Admin with success alert
        header('Location: pos.php?xml_synced=1');
        exit;
    } catch (Throwable $e) {
        if (isset($_GET['format']) && $_GET['format'] === 'json') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        die('XML Sync Error: ' . htmlspecialchars($e->getMessage()));
    }
}