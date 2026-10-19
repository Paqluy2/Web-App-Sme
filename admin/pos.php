<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/inventory.php';

// Authenticate session permissions
Auth::requireStaff();

// Pull drink catalog from menu_product table
$catalog = CafeInventory::getMenuCatalog();
$categories = array_filter(array_unique(array_column($catalog, 'Product_type')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gigs Cafe - POS Terminal</title>
    <!-- Base styles and isolated POS stylesheet -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pos.css">
</head>
<body class="pos-layout-body">
    <div class="pos-container">

        <!-- Catalog Deck -->
        <main class="pos-deck">
            <header class="pos-header">
                <div>
                    <h2>☕ Gigs Cafe POS Register</h2>
                    <p>Cashier: <strong><?= sanitize($_SESSION['name'] ?? $_SESSION['username']) ?></strong></p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-secondary btn-sm">Dashboard</a>
                    <a href="logout.php" class="btn btn-sm" style="background: var(--danger, #ef4444); color: #fff; margin-left: 6px;">Logout</a>
                </div>
            </header>

            <div class="pos-search">
                <input type="text" id="posFilter" class="form-control" placeholder="Search drink name or type (e.g., Wintermelon, Matcha, 22oz)...">
            </div>

            <!-- Dynamic Category Navigation Tabs -->
            <div class="category-pills">
                <button type="button" class="pill-btn active" data-cat="all">All Items</button>
                <?php foreach ($categories as $cat): ?>
                    <button type="button" class="pill-btn" data-cat="<?= sanitize($cat) ?>"><?= sanitize($cat) ?></button>
                <?php endforeach; ?>
            </div>

            <!-- Dynamic Product Grid -->
            <div class="pos-grid" id="productDeck">
                <?php foreach ($catalog as $item): ?>
                <div class="pos-item-card" 
                     data-id="<?= (int)$item['Product_id'] ?>" 
                     data-name="<?= sanitize($item['Product_name']) ?>" 
                     data-type="<?= sanitize($item['Product_type']) ?>" 
                     data-price="<?= (float)$item['Retail_price'] ?>">
                    <h4><?= sanitize($item['Product_name']) ?></h4>
                    <p class="sku"><?= sanitize($item['Product_type']) ?></p>
                    <div class="pos-card-footer">
                        <span class="price">₱<?= number_format((float)$item['Retail_price'], 2) ?></span>
                        <span class="add-action">+ Add</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </main>

        <!-- Ticket Tape & Tender Controls -->
        <aside class="pos-tape">
            <h3>Active Ticket</h3>

            <div class="pos-items-table-wrapper">
                <table class="pos-items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th style="width: 72px;">Qty</th>
                            <th>Price</th>
                            <th>Total</th>
                            <th style="width: 24px;"></th>
                        </tr>
                    </thead>
                    <tbody id="posTapeBody">
                        <!-- Populated by assets/js/pos.js -->
                    </tbody>
                </table>
            </div>

            <div class="pos-summary">
                <div class="summary-line">
                    <span>Subtotal:</span>
                    <span id="posSubtotal">₱0.00</span>
                </div>
                <div class="summary-line">
                    <span>VAT Included (8%):</span>
                    <span id="posTax">₱0.00</span>
                </div>
                <div class="summary-line grand-total">
                    <span>Grand Total:</span>
                    <span id="posGrandTotal">₱0.00</span>
                </div>
            </div>

            <div class="pos-controls">
                <div class="form-group">
                    <label for="paymentMethod">Payment Tender</label>
                    <select id="paymentMethod" class="form-control">
                        <option value="Cash">Cash</option>
                        <option value="GCash">GCash / Maya</option>
                        <option value="Card">Debit / Credit Card</option>
                    </select>
                </div>

                <div class="form-group" id="tenderGroup">
                    <label for="tenderedAmount">Amount Tendered (₱)</label>
                    <input type="number" step="any" id="tenderedAmount" class="form-control" placeholder="0.00">

                    <div class="quick-cash-row">
                        <button type="button" class="quick-cash-btn" data-val="exact">Exact</button>
                        <button type="button" class="quick-cash-btn" data-val="100">₱100</button>
                        <button type="button" class="quick-cash-btn" data-val="200">₱200</button>
                        <button type="button" class="quick-cash-btn" data-val="500">₱500</button>
                        <button type="button" class="quick-cash-btn" data-val="1000">₱1000</button>
                    </div>

                    <div class="change-display">
                        <span>Change Due:</span>
                        <span id="posChange">₱0.00</span>
                    </div>
                </div>

                <button type="button" id="btnCompleteSale" class="btn btn-primary btn-block btn-lg">Finalize Sale</button>
            </div>
        </aside>
    </div>

    <!-- Deferred Client Engine Execution -->
    <script src="../assets/js/pos.js" defer></script>
</body>
</html>