<?php
declare(strict_types=1);

// 1. Require database and authentication classes
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// 2. Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Verify admin authentication
if (!Auth::check()) {
    header('Location: login.php');
    exit;
}

// 4. Load the POS layout
require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. DASHBOARD SECTION -->
<section id="tab-dashboard" class="section-pane active">
    <!-- Top 4 KPI Metric Cards -->
    <div class="dashboard-grid">
        <div class="kpi-card">
            <div class="label">Total Combined Revenue</div>
            <div class="value">₱<?= number_format($combinedRevenue, 2) ?></div>
            <div class="sub">POS + Online Orders</div>
        </div>
        <div class="kpi-card">
            <div class="label">Today's POS Sales</div>
            <div class="value" style="color:var(--primary);">₱<?= number_format($todayRevenue, 2) ?></div>
            <div class="sub"><?= count($sales) ?> walk-in transactions</div>
        </div>
        <div class="kpi-card">
            <div class="label">Pending Online Orders</div>
            <div class="value" style="color:var(--accent);"><?= $pendingOnlineCount ?></div>
            <div class="sub"><?= count($onlineOrders) ?> total web orders</div>
        </div>
        <div class="kpi-card">
            <div class="label">Low Stock Alerts</div>
            <div class="value" style="color:var(--danger);"><?= $lowStockCount ?></div>
            <div class="sub">Out of <?= count($inventory) ?> tracked items</div>
        </div>
    </div>

    <!-- Analytics Chart Section -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
            <div>
                <h3 id="chartTitleDisplay" style="font-size: 1.1rem;">📈 Sales Analytics — Daily Sales (Last 7 Days)
                </h3>
                <span style="font-size:0.8rem; color:var(--text-muted);">Combined Walk-In POS (`salestb`) & Online
                    Orders (`online_orders`)</span>
            </div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap: wrap;">
                <span id="chartPeriodSum"
                    style="font-weight:800; color:var(--primary); margin-right:6px; font-size:0.95rem;"></span>
                <button class="btn btn-primary period-btn" id="btn-chart-daily" onclick="updateSalesChart('daily')"
                    style="padding: 6px 12px; font-size: 0.8rem;">Daily</button>
                <button class="btn btn-outline period-btn" id="btn-chart-weekly" onclick="updateSalesChart('weekly')"
                    style="padding: 6px 12px; font-size: 0.8rem;">Weekly</button>
                <button class="btn btn-outline period-btn" id="btn-chart-monthly" onclick="updateSalesChart('monthly')"
                    style="padding: 6px 12px; font-size: 0.8rem;">Monthly</button>
            </div>
        </div>
        <div style="position: relative; height: 280px; width: 100%;">
            <canvas id="salesAnalyticsCanvas"></canvas>
        </div>
    </div>

    <!-- Recent Tables Row -->
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
        <div class="card" style="margin-bottom:0;">
            <div class="card-header">
                <h3>Recent Walk-In POS Sales</h3>
                <button class="btn btn-outline" onclick="document.getElementById('nav-sales').click()"
                    style="padding: 5px 10px; font-size: 0.78rem;">View All</button>
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($sales, 0, 5) as $s): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars((string) $s['ord_no']) ?></strong></td>
                                <td><?= htmlspecialchars((string) $s['items']) ?></td>
                                <td>₱<?= number_format((float) $s['total'], 2) ?></td>
                                <td style="font-size: 0.8rem; color: var(--text-muted);">
                                    <?= htmlspecialchars((string) $s['date']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($sales)): ?>
                            <tr>
                                <td colspan="4" style="text-align:center; color:var(--text-muted);">No POS transactions yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card" style="margin-bottom:0;">
            <div class="card-header">
                <h3>Low Stock Ingredients</h3>
                <button class="btn btn-outline" onclick="document.getElementById('nav-inventory').click()"
                    style="padding: 5px 10px; font-size: 0.78rem;">Manage Stocks</button>
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Ingredient</th>
                            <th>Current Stock</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $shownLow = 0; // <-- Initialize variable here
                        $previewCount = 0;
                        foreach ($inventory as $inv):
                            if ($inv['current_stock'] <= $inv['min']):
                                $shownLow++;
                                $previewCount++;
                                if ($previewCount > 5)
                                    continue;
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars((string) $inv['material_name']) ?></strong></td>
                                    <td><?= number_format((float) $inv['current_stock'], 2) ?>
                                        <?= htmlspecialchars((string) $inv['unit']) ?>
                                    </td>
                                    <td>
                                        <span
                                            class="status-pill <?= $inv['current_stock'] <= 0 ? 'status-out' : 'status-low' ?>">
                                            <?= $inv['current_stock'] <= 0 ? 'Out of Stock' : 'Low Stock' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endif; endforeach; ?>
                        <?php if ($shownLow === 0): ?>
                            <tr>
                                <td colspan="3" style="text-align:center; color:var(--success);">All ingredients are well
                                    stocked!</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- 2. POINT OF SALE (POS) SECTION -->
<section id="tab-pos" class="section-pane">
    <div class="pos-container">
        <div>
            <div style="display:flex; justify-content:space-between; gap:12px; margin-bottom:14px;">
                <input type="text" id="posSearch" class="form-control" placeholder="🔍 Search drink name..."
                    oninput="renderPosProducts()" style="max-width:320px; margin-top:0;">
            </div>
            <div class="cat-bar" id="posCategories"></div>
            <div class="product-grid" id="posProductGrid"></div>
        </div>

        <div class="pos-ticket">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3>Current Ticket</h3>
                <button class="btn btn-outline" onclick="clearPosCart()"
                    style="padding:4px 10px; font-size:0.78rem;">Clear</button>
            </div>

            <div class="cart-list" id="posCartList">
                <p style="text-align:center; color:var(--text-muted); padding:30px 0;">Select drinks from the left to
                    build an order.</p>
            </div>

            <div style="border-top:2px solid var(--border); padding-top:12px;">
                <div
                    style="display:flex; justify-content:space-between; font-size:1.15rem; font-weight:800; margin-bottom:12px;">
                    <span>Grand Total:</span>
                    <span id="posTotalDisplay" style="color:var(--primary);">₱0.00</span>
                </div>

                <div style="margin-bottom:10px;">
                    <label style="font-size:0.8rem; font-weight:700;">Payment Method</label>
                    <select id="posPaymentMethod" class="form-control" onchange="updatePosChange()">
                        <option value="Cash">Cash</option>
                        <option value="GCash">GCash / E-Wallet</option>
                    </select>
                </div>

                <div style="margin-bottom:10px;">
                    <label style="font-size:0.8rem; font-weight:700;">Amount Tendered (₱)</label>
                    <input type="number" id="posCashInput" class="form-control" placeholder="0.00" step="0.01"
                        oninput="updatePosChange()">
                </div>

                <div
                    style="display:flex; justify-content:space-between; font-size:0.95rem; font-weight:700; margin-bottom:14px; color:var(--success);">
                    <span>Change:</span>
                    <span id="posChangeDisplay">₱0.00</span>
                </div>

                <button class="btn btn-primary" style="width:100%; padding:12px; font-size:1rem;"
                    onclick="submitPosOrder()">
                    ✅ Complete Sale & Print Receipt
                </button>
            </div>
        </div>
    </div>
</section>

<!-- 3. SALES TRACKING & ONLINE ORDERS SECTION -->
<section id="tab-sales" class="section-pane">
    <div class="card">
        <div class="card-header">
            <h3>🌐 Online Customer Orders (Live Queue)</h3>
        </div>
<table>
    <thead>
        <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Phone</th>
            <th>IP Address</th>
            <th>Items Ordered</th>
            <th>Fulfillment</th>
            <th>Total</th>
            <th>Status</th>
            <th>Update Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($onlineOrders as $o): ?>
            <tr>
                <td><strong><?= htmlspecialchars((string) $o['ord_no']) ?></strong></td>
                <td><?= htmlspecialchars((string) $o['cust']) ?></td>
                <td><?= htmlspecialchars((string) $o['phone']) ?></td>
                
                <!-- IP ADDRESS COLUMN -->
                <td>
                    <span style="font-family: monospace; background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 4px; user-select: all;">
                        <?= !empty($o['ip_address']) ? htmlspecialchars((string) $o['ip_address']) : 'No IP' ?>
                    </span>
                </td>

                <!-- ITEMS ORDERED COLUMN -->
<td>
                    <ul style="margin: 0; padding-left: 15px; font-size: 0.85rem; color: #334155;">
                        <?= $o['items_html'] ?>
                    </ul>
                </td>
                
                <!-- FULFILLMENT COLUMN -->
                <td>
                    <?= htmlspecialchars((string) $o['type']) ?>
                    <?php if (!empty($o['addr'])): ?>
                        <small style="display:block; color:var(--text-muted);"><?= htmlspecialchars((string) $o['addr']) ?></small>
                    <?php endif; ?>
                </td>
                
                <!-- TOTAL COLUMN -->
                <td><strong>₱<?= number_format((float) $o['total'], 2) ?></strong></td>
                
                <!-- STATUS COLUMN -->
                <td><span class="status-pill status-low"><?= htmlspecialchars((string) $o['status']) ?></span></td>
                
                <!-- UPDATE STATUS COLUMN -->
                <td>
                    <select class="form-control"
                        style="padding:5px 8px; font-size:0.85rem; width:auto; border-radius:6px; border:1px solid #cbd5e1; outline:none;"
                        onchange="updateOnlineStatus(<?= (int) $o['id'] ?>, this.value)">
                        <?php
                        $isPickup = strtolower((string) ($o['type'] ?? 'pickup')) === 'pickup';
                        $statusList = $isPickup
                            ? ['Pending', 'Preparing', 'Ready for Pickup', 'Completed', 'Cancelled']
                            : ['Pending', 'Preparing', 'In Delivery', 'Completed', 'Cancelled'];

                        foreach ($statusList as $st):
                            ?>
                            <option value="<?= htmlspecialchars($st) ?>"
                                <?= (strtolower(trim((string) $o['status'])) === strtolower(trim($st))) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($st) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>🧾 Walk-In POS Sales History </h3>
            <div style="display:flex; gap:10px; align-items:center;">
                <input type="text" id="salesSearchInput" class="form-control" placeholder="Search order # or item..."
                    oninput="filterSalesTable()" style="width:220px; margin:0;">
                <button class="btn btn-success" onclick="exportSalesCSV()">⬇ Export CSV</button>
            </div>
        </div>
        <table id="posSalesTable">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Items Sold</th>
                    <th>Qty</th>
                    <th>Total Amount</th>
                    <th>Method</th>
                    <th>Date & Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $s): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars((string) $s['ord_no']) ?></strong></td>
                        <td><?= htmlspecialchars((string) $s['items']) ?></td>
                        <td><?= (int) $s['qty'] ?></td>
                        <td><strong>₱<?= number_format((float) $s['total'], 2) ?></strong></td>
                        <td><?= htmlspecialchars((string) $s['method']) ?></td>
                        <td><?= htmlspecialchars((string) $s['date']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- 4. INVENTORY (STOCKS) SECTION -->
<section id="tab-inventory" class="section-pane">
    <div class="card">
        <div class="card-header">
            <h3>📦 Ingredient & Packaging Stocks </h3>
            <div style="display:flex; gap:10px;">
                <input type="text" id="invSearchInput" class="form-control" placeholder="Search ingredient..."
                    oninput="filterInventoryTable()" style="width:220px; margin:0;">
                <button class="btn btn-primary" onclick="openInvModal()">+ Add New Ingredient</button>
                <button class="btn btn-outline" onclick="lockInventory()"
                    style="margin-left:8px; border-color:#cbd5e1; color:#64748b;">
                    <img src="../images/lock.png" alt="Lock Icon" style="width: 16px; height: 12px; object-fit: contain;">Lock Stocks
                </button>
            </div>
        </div>


        <table id="inventoryTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Ingredient / Item Name</th>
                    <th>Current Stock</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inventory as $inv): ?>
                    <tr>
                        <td>#<?= (int) $inv['material_id'] ?></td>
                        <td><strong><?= htmlspecialchars((string) $inv['material_name']) ?></strong></td>
                        <td style="font-weight:700;"><?= number_format((float) $inv['current_stock'], 2) ?></td>
                        <td><?= htmlspecialchars((string) $inv['unit']) ?></td>
                        <td>
                            <?php if ($inv['current_stock'] <= 0): ?>
                                <span class="status-pill status-out">Out of Stock</span>
                            <?php elseif ($inv['current_stock'] <= $inv['min']): ?>
                                <span class="status-pill status-low">Low Stock</span>
                            <?php else: ?>
                                <span class="status-pill status-ok">In Stock</span>
                            <?php endif; ?>
                        </td>
                        <td style="display:flex; gap:6px;">
                            <button class="btn btn-success"
                                onclick="promptRestock(<?= (int) $inv['material_id'] ?>, '<?= htmlspecialchars(addslashes((string) $inv['material_name'])) ?>')">+
                                Restock</button>
                            <button class="btn btn-outline"
                                onclick="openInvModal(<?= (int) $inv['material_id'] ?>, '<?= htmlspecialchars(addslashes((string) $inv['material_name'])) ?>', <?= (float) $inv['current_stock'] ?>, '<?= htmlspecialchars(addslashes((string) $inv['unit'])) ?>')">Edit</button>
                            <button class="btn btn-danger"
                                onclick="deleteInvItem(<?= (int) $inv['material_id'] ?>)">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<!-- MANAGER SECURITY PIN MODAL FOR INVENTORY -->
<div id="managerPinModal"
    style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.75); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
    <div
        style="background:#fff; width:100%; max-width:380px; border-radius:14px; padding:24px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.3); text-align:center;">
        <div
            style="width:54px; height:54px; background:#fee2e2; color:#ef4444; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 14px; font-size:1.6rem;">
            🔒
        </div>
        <h3 style="margin:0 0 6px; font-size:1.25rem; color:#1e293b;">Manager Authorization</h3>
        <p style="margin:0 0 18px; font-size:0.875rem; color:#64748b;">
            Inventory and ingredient stocks are restricted. Enter the manager PIN code to proceed.
        </p>

        <form id="managerPinForm" onsubmit="event.preventDefault(); verifyManagerPin();">
            <input type="password" id="managerPinInput" maxlength="6" placeholder="Enter 4-digit PIN" autocomplete="off"
                style="width:100%; text-align:center; font-size:1.4rem; letter-spacing:8px; padding:10px; border:2px solid #cbd5e1; border-radius:8px; outline:none; margin-bottom:14px;">
            <div id="pinErrorMsg"
                style="display:none; color:#dc2626; font-size:0.82rem; margin-bottom:12px; font-weight:600;">
                Incorrect security PIN. Access denied.
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-outline" style="flex:1; padding:10px;"
                    onclick="cancelManagerPin()">Cancel</button>
                <button type="submit" class="btn btn-primary"
                    style="flex:1; padding:10px; background:#2563eb; color:#fff; border:none; border-radius:8px; cursor:pointer;">Authorize</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>