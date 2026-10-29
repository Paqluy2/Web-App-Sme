<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';
$db = getDB();

function safeQuery(PDO $db, string $sql, array $params = []): array
{
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

// 1. Load Menu Products directly for POS (Using exact menu_product column names)
$productsRaw = safeQuery($db, "SELECT Product_id, Product_type, Product_name, Retail_price, size, image FROM menu_product ORDER BY Product_id ASC");
$drinksGrouped = [];

foreach ($productsRaw as $r) {
    $pId = (int) $r['Product_id'];
    $category = (string) ($r['Product_type'] ?? 'Drinks');
    $name = (string) ($r['Product_name'] ?? 'Drink');
    $sizeRaw = strtolower(trim((string) ($r['size'] ?? '16oz')));
    $price = (float) ($r['Retail_price'] ?? 0.0);

    if (!isset($drinksGrouped[$name])) {
        $drinksGrouped[$name] = [
            'name' => $name,
            'category' => $category,
            'id_16oz' => null,
            'price_16oz' => 0.0,
            'id_22oz' => null,
            'price_22oz' => 0.0,
        ];
    }

    if (str_contains($sizeRaw, '22')) {
        $drinksGrouped[$name]['id_22oz'] = $pId;
        $drinksGrouped[$name]['price_22oz'] = $price;
    } else {
        $drinksGrouped[$name]['id_16oz'] = $pId;
        $drinksGrouped[$name]['price_16oz'] = $price;
    }
}

$posProducts = array_values($drinksGrouped);

// Ensure both sizes have realistic prices even if only one size row exists in the database
$posProducts = [];
foreach ($drinksGrouped as $d) {
    if ($d['price_16oz'] <= 0 && $d['price_22oz'] > 0) {
        $d['price_16oz'] = max(20.0, $d['price_22oz'] - 15.0);
    } elseif ($d['price_22oz'] <= 0 && $d['price_16oz'] > 0) {
        $d['price_22oz'] = $d['price_16oz'] + 15.0;
    }
    $posProducts[] = $d;
}
// 2. Load Inventory (Stocks)
$inventoryRaw = safeQuery($db, "SELECT `material_id`, `material_name`, `current_stock`, `unit`, `category` FROM inventory");
$inventory = [];
$lowStockCount = 0;

foreach ($inventoryRaw as $row) {
    $id = (int) $row['material_id'];
    $name = (string) $row['material_name'];
    $qty = (float) $row['current_stock'];
    $unit = (string) $row['unit'];
    $min = 20.0;

    if ($qty <= $min) {
        $lowStockCount++;
    }

    $inventory[] = [
        'material_id' => $id,
        'material_name' => $name,
        'current_stock' => $qty,
        'unit' => $unit,
        'min' => $min
    ];
}

// 3. Load Walk-In POS Sales (salestb)
$salesRaw = safeQuery($db, "
    SELECT s.*, m.Product_name 
    FROM salestb s 
    LEFT JOIN menu_product m ON s.product_id = m.Product_id 
    LEFT JOIN online_orders o ON s.transaction_code = o.transaction_code
    WHERE o.transaction_code IS NULL 
       OR o.order_status = 'Completed'
    ORDER BY s.sold_date DESC 
    LIMIT 200
");

$sales = [];
$totalPosRevenue = 0.0;
$todayRevenue = 0.0;
$todayDate = date('Y-m-d');

foreach ($salesRaw as $row) {
    $keys = array_keys($row);
    $id = $row[$keys[0]] ?? 0;
    
    $ordNo = $row['transaction_code'] ?? ('SALE-' . $id);
    $items = $row['Product_name'] ?? 'Walk-in Order';
    $qty = (int) ($row['quantity_sold'] ?? 1);
    $total = (float) ($row['total_amount'] ?? 0.0);
    $method = $row['payment_method'] ?? 'Cash';
    $date = $row['sold_date'] ?? date('Y-m-d H:i:s');

    $totalPosRevenue += $total;
    if (str_starts_with((string) $date, $todayDate)) {
        $todayRevenue += $total;
    }

    $sales[] = [
        'id' => $id,
        'ord_no' => $ordNo,
        'items' => $items,
        'qty' => $qty,
        'total' => $total,
        'method' => $method,
        'date' => $date
    ];
}
// 4. Load Online Orders (Joined with customers)
$onlineRaw = safeQuery($db, "
    SELECT 
        o.order_id,
        o.transaction_code,
        o.order_status,
        o.fulfillment_type,
        o.delivery_address,
        o.grand_total,
        o.created_at,
        o.ip_address, 
        c.full_name,
        c.phone
    FROM online_orders o
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    WHERE DATE(o.created_at) = CURDATE() 
      AND o.order_status NOT IN ('Completed', 'Cancelled', 'Archived')
    ORDER BY o.order_id DESC
");

$onlineOrders = [];
$pendingOnlineCount = 0;
$totalOnlineRevenue = 0.0;

foreach ($onlineRaw as $row) {
    $id = (int) $row['order_id'];
    $ordNo = !empty($row['transaction_code']) ? (string) $row['transaction_code'] : ('WEB-' . $id);
    $cust = !empty($row['full_name']) ? (string) $row['full_name'] : 'Guest Customer';
    $phone = !empty($row['phone']) ? (string) $row['phone'] : 'N/A';
    $type = (string) ($row['fulfillment_type'] ?? 'Pickup');
    $addr = (string) ($row['delivery_address'] ?? '');
    $total = (float) ($row['grand_total'] ?? 0.0);
    $status = (string) ($row['order_status'] ?? 'Pending');
    $date = (string) ($row['created_at'] ?? date('Y-m-d H:i:s'));
    $ip = (string) ($row['ip_address'] ?? '');
    $stmtItems = $db->prepare("
        SELECT m.Product_name, s.quantity_sold 
        FROM salestb s 
        LEFT JOIN menu_product m ON s.product_id = m.Product_id 
        WHERE s.transaction_code = ?
    ");
    $stmtItems->execute([$ordNo]);
    $orderItems = $stmtItems->fetchAll();

    // Build the HTML list of items (e.g., "2x Matcha Tea Latte")
    $itemsHtml = '';
    foreach ($orderItems as $item) {
        $itemsHtml .= "<li>" . (int)$item['quantity_sold'] . "x " . htmlspecialchars((string)$item['Product_name']) . "</li>";
    }
    if (empty($itemsHtml)) {
        $itemsHtml = "<li><em>No items found</em></li>";
    }

    if (strtolower($status) === 'pending') {
        $pendingOnlineCount++;
    }
    if (strtolower($status) !== 'cancelled') {
        $totalOnlineRevenue += $total;
    }

    $onlineOrders[] = [
        'id' => $id,
        'ord_no' => $ordNo,
        'cust' => $cust,
        'phone' => $phone,
        'type' => $type,
        'addr' => $addr,
        'total' => $total,
        'status' => $status,
        'date' => $date,
        'ip_address' => $ip,
        'items_html' => $itemsHtml
    ];
}
require_once __DIR__ . '/../export_xml.php';
syncAllXmlFiles($db);
$combinedRevenue = $totalPosRevenue + $totalOnlineRevenue;
// 5. Build Sales Analytics Data (Daily: Last 7 Days, Weekly: Last 4 Weeks, Monthly: 12 Months)
$allValidSales = [];

// Include Walk-in POS Sales
foreach ($sales as $s) {
    $ts = strtotime((string) $s['date']);
    if ($ts !== false) {
        $allValidSales[] = ['ts' => $ts, 'total' => (float) $s['total']];
    }
}

// Include Non-Cancelled Online Orders
foreach ($onlineOrders as $o) {
    if (strtolower((string) $o['status']) !== 'cancelled') {
        $ts = strtotime((string) $o['date']);
        if ($ts !== false) {
            $allValidSales[] = ['ts' => $ts, 'total' => (float) $o['total']];
        }
    }
}

// A. Daily Analytics (Last 7 Days)
$dailyLabels = [];
$dailyTotals = [];
for ($i = 6; $i >= 0; $i--) {
    $dayStr = date('Y-m-d', strtotime("-$i days"));
    $dailyLabels[] = date('M d (D)', strtotime($dayStr));
    $sum = 0.0;
    foreach ($allValidSales as $sale) {
        if (date('Y-m-d', $sale['ts']) === $dayStr) {
            $sum += $sale['total'];
        }
    }
    $dailyTotals[] = round($sum, 2);
}

// B. Weekly Analytics (Last 4 Weeks)
$weeklyLabels = [];
$weeklyTotals = [];
for ($w = 3; $w >= 0; $w--) {
    $startOfWeek = strtotime("monday this week -$w weeks");
    $endOfWeek = strtotime("sunday this week -$w weeks 23:59:59");
    $weeklyLabels[] = date('M d', $startOfWeek) . ' - ' . date('M d', $endOfWeek);
    $sum = 0.0;
    foreach ($allValidSales as $sale) {
        if ($sale['ts'] >= $startOfWeek && $sale['ts'] <= $endOfWeek) {
            $sum += $sale['total'];
        }
    }
    $weeklyTotals[] = round($sum, 2);
}

// C. Monthly Analytics (Jan - Dec of Current Year)
$monthlyLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$monthlyTotals = array_fill(0, 12, 0.0);
$currentYear = date('Y');
foreach ($allValidSales as $sale) {
    if (date('Y', $sale['ts']) === $currentYear) {
        $monthIdx = (int) date('n', $sale['ts']) - 1;
        $monthlyTotals[$monthIdx] = round($monthlyTotals[$monthIdx] + $sale['total'], 2);
    }
}

$analyticsChartData = [
    'daily' => ['labels' => $dailyLabels, 'data' => $dailyTotals, 'title' => 'Daily Sales (Last 7 Days)'],
    'weekly' => ['labels' => $weeklyLabels, 'data' => $weeklyTotals, 'title' => 'Weekly Sales (Last 4 Weeks)'],
    'monthly' => ['labels' => $monthlyLabels, 'data' => $monthlyTotals, 'title' => "Monthly Sales ($currentYear)"]
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>GIGS CAFÉ — Administrator </title>
    <style>
        :root {
            --bg-dark: #0f172a;
            --sidebar-bg: #1e293b;
            --card-bg: #ffffff;
            --primary: #1d4ed8;
            --primary-hover: #1e40af;
            --accent: #d97706;
            --success: #16a34a;
            --danger: #dc2626;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        body {
            display: flex;
            min-height: 100vh;
            background: #f1f5f9;
            color: var(--text-main);
        }

        .sidebar {
            width: 250px;
            background: var(--sidebar-bg);
            color: #fff;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }

        .brand {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .brand-logo {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.1rem;
        }

        .brand h2 {
            font-size: 1.15rem;
            font-weight: 700;
        }

        .brand span {
            font-size: 0.75rem;
            color: #94a3b8;
            display: block;
        }

        .nav-links {
            list-style: none;
            padding: 15px 10px;
            flex: 1;
        }

        .nav-links li {
            margin-bottom: 6px;
        }

        .nav-btn {
            width: 100%;
            text-align: left;
            background: none;
            border: none;
            color: #cbd5e1;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s;
        }

        .nav-btn:hover,
        .nav-btn.active {
            background: var(--primary);
            color: #fff;
        }

        .badge-count {
            background: var(--danger);
            color: #fff;
            font-size: 0.72rem;
            padding: 2px 7px;
            border-radius: 99px;
            font-weight: 700;
        }

        .sidebar-footer {
            padding: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logout-link {
            display: block;
            text-align: center;
            padding: 10px;
            border-radius: 6px;
            background: rgba(220, 38, 38, 0.2);
            color: #fca5a5;
            text-decoration: none;
            font-weight: 600;
        }

        .logout-link:hover {
            background: var(--danger);
            color: #fff;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        .topbar {
            background: #fff;
            padding: 15px 28px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar h1 {
            font-size: 1.35rem;
            font-weight: 700;
        }

        .section-pane {
            display: none;
            padding: 24px 28px;
        }

        .section-pane.active {
            display: block;
        }

        /* Dashboard Layout Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }

        @media (max-width: 1300px) {
            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .kpi-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .kpi-card .label {
            font-size: 0.80rem;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 700;
        }

        .kpi-card .value {
            font-size: 1.6rem;
            font-weight: 800;
            margin-top: 8px;
            color: var(--text-main);
            word-break: break-all;
        }

        .kpi-card .sub {
            font-size: 0.78rem;
            margin-top: 4px;
            color: var(--text-muted);
        }

        /* Main Dashboard Main Row (Chart + Side KPIs or Full Width Chart) */
        .analytics-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        @media (max-width: 1100px) {
            .analytics-row {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 20px;
            margin-bottom: 22px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .kpi-card .label {
            font-size: 0.82rem;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 700;
        }

        .kpi-card .value {
            font-size: 1.75rem;
            font-weight: 800;
            margin-top: 8px;
            color: var(--text-main);
        }

        .kpi-card .sub {
            font-size: 0.8rem;
            margin-top: 4px;
            color: var(--text-muted);
        }

        .card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 20px;
            margin-bottom: 22px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        th,
        td {
            padding: 11px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: #f8fafc;
            font-weight: 700;
            color: #475569;
            font-size: 0.8rem;
            text-transform: uppercase;
        }

        tr:hover {
            background: #f8fafc;
        }

        .btn {
            padding: 8px 15px;
            border-radius: 7px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.85rem;
            transition: 0.15s;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .btn-success {
            background: var(--success);
            color: #fff;
        }

        .btn-danger {
            background: var(--danger);
            color: #fff;
        }

        .btn-outline {
            background: #f1f5f9;
            color: var(--text-main);
            border: 1px solid var(--border);
        }

        .status-pill {
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
        }

        .status-ok {
            background: #dcfce7;
            color: #15803d;
        }

        .status-low {
            background: #fef3c7;
            color: #b45309;
        }

        .status-out {
            background: #fee2e2;
            color: #b91c1c;
        }

        .pos-container {
            display: grid;
            grid-template-columns: 1fr 370px;
            gap: 20px;
            align-items: start;
        }

        .cat-bar {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .cat-pill {
            padding: 7px 14px;
            border-radius: 99px;
            border: 1px solid var(--border);
            background: #fff;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.83rem;
        }

        .cat-pill.active {
            background: var(--bg-dark);
            color: #fff;
            border-color: var(--bg-dark);
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(175px, 1fr));
            gap: 14px;
        }

        .prod-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .prod-card h4 {
            font-size: 0.95rem;
            margin-bottom: 4px;
        }

        .prod-card .cat-tag {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        .size-btns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin-top: 8px;
        }

        .size-btn {
            padding: 7px 4px;
            border: 1px solid var(--primary);
            background: #eff6ff;
            color: var(--primary);
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
        }

        .size-btn:hover {
            background: var(--primary);
            color: #fff;
        }

        .pos-ticket {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px;
            position: sticky;
            top: 20px;
        }

        .cart-list {
            max-height: 310px;
            overflow-y: auto;
            margin: 12px 0;
            border-top: 1px solid var(--border);
        }

        .cart-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            font-size: 0.88rem;
        }

        .qty-controls button {
            width: 24px;
            height: 24px;
            border: 1px solid var(--border);
            background: #f8fafc;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 700;
        }

        .form-control {
            width: 100%;
            padding: 9px 11px;
            border: 1px solid var(--border);
            border-radius: 7px;
            font-size: 0.9rem;
            margin-top: 4px;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-box {
            background: #fff;
            width: 95%;
            max-width: 420px;
            border-radius: 12px;
            padding: 22px;
        }

        @media print {

            /* Hide everything on the page except the receipt */
            body * {
                visibility: hidden !important;
            }

            #receiptModal,
            #receiptModal * {
                visibility: visible !important;
            }

            #receiptModal {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                background: transparent !important;
                box-shadow: none !important;
            }

            /* Set width for standard 58mm or 80mm thermal paper rolls */
            #receiptBody {
                width: 58mm;
                /* or 80mm depending on printer model */
                font-family: 'Courier New', monospace;
                color: #000 !important;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }
        }

        .status-pill.status-ready-for-pickup,
        .badge-ready-for-pickup {
            background-color: #dbeafe !important;
            color: #1d4ed8 !important;
            border: 1px solid #bfdbfe;
        }


        @media (max-width: 768px) {
    /* 1. Stack the Sidebar on top of the content */
    body {
        flex-direction: column !important;
        display: flex;
    }
    
    .sidebar {
        width: 100% !important;
        height: auto !important;
        position: relative !important;
        display: flex;
        flex-direction: row;
        overflow-x: auto; /* Lets admin scroll navigation left/right */
        padding: 10px;
    }

    /* Keep the logo small and navigation horizontal on phones */
    .sidebar .brand { margin-bottom: 0; margin-right: 15px; }
    .sidebar .nav-links { display: flex; flex-direction: row; gap: 10px; margin: 0; }
    .sidebar .nav-links li { margin: 0; white-space: nowrap; }

    /* 2. Give the main content room to breathe */
    .main-content {
        width: 100% !important;
        padding: 15px !important;
        margin-left: 0 !important;
    }

    /* 3. Stop wide data tables from breaking the screen width */
    table {
        display: block;
        width: 100%;
        overflow-x: auto; /* Allows swiping left/right on the table only */
        white-space: nowrap;
    }

    /* 4. Fix POS Product Grid to 2 items per row instead of 4+ */
    .product-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 10px !important;
    }

    /* 5. Shrink Pop-up Modals to fit the phone screen */
    .modal-content, 
    .receipt-modal {
        width: 95% !important;
        margin: 20px auto !important;
        padding: 15px !important;
    }
}
    </style>
</head>

<body>

    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo"><img src="../images/logocafe.jpg" alt="Gigs Cafe Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;"></div>
            
            <div>
                <h2>GIGS CAFÉ</h2>
                <span>Admin & POS System</span>
            </div>
        </div>

        <ul class="nav-links">
            <li>
                <a href="export_xml.php" class="nav-btn" style="color:#38bdf8; text-decoration:none;">
                    <span>🔄 Sync XML Files</span>
                </a>
            </li>
            <li>
                <button class="nav-btn active" id="nav-dashboard" onclick="switchTab('dashboard', this)">
                    <span>📊 Dashboard</span>
                </button>
            </li>
            <li>
                <button class="nav-btn" id="nav-pos" onclick="switchTab('pos', this)">
                    <span>🖥️ Point of Sale (POS)</span>
                </button>
            </li>
            <li>
                <button class="nav-btn" id="nav-sales" onclick="switchTab('sales', this)">
                    <span>📈 Sales Tracking</span>
                    <?php if ($pendingOnlineCount > 0): ?>
                        <span class="badge-count"><?= $pendingOnlineCount ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li>
                <button class="nav-btn" id="nav-inventory" onclick="switchTab('inventory', this)">
                    <span>📦 Inventory (Stocks)</span>
                    <?php if ($lowStockCount > 0): ?>
                        <span class="badge-count"><?= $lowStockCount ?></span>
                    <?php endif; ?>
                </button>
            </li>
        </ul>

        <div class="sidebar-footer">
            <a href="../index.php"
                style="display:block; text-align:center; color:#94a3b8; font-size:0.82rem; margin-bottom:10px; text-decoration:none;">←
                View Storefront</a>
            <a href="logout.php" class="logout-link">Sign Out</a>
        </div>
    </aside>

    <div class="main-content">
        <header class="topbar">
            <h1 id="pageTitle">Dashboard Overview</h1>
            <div style="font-size:0.88rem; color:var(--text-muted);">
                Logged in as <strong><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator') ?></strong> |
                <?= date('F d, Y') ?>
            </div>

            <!-- Receipt Modal -->
            <div id="receiptModal"
                style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center;">
                <div
                    style="background:#fff; width:340px; border-radius:12px; padding:20px; box-shadow:0 10px 25px rgba(0,0,0,0.2);">

                    <!-- Printable Body Area -->
                    <div id="receiptBody"></div>

                    <!-- Buttons (Hidden during actual print) -->
                    <div class="no-print" style="display:flex; gap:8px; margin-top:16px;">
                        <button class="btn btn-outline" style="flex:1;"
                            onclick="document.getElementById('receiptModal').style.display='none'">Close</button>
                        <button class="btn btn-primary" style="flex:1;" onclick="window.print()">🖨️ Print
                            Receipt</button>
                    </div>
                </div>
            </div>
        </header>