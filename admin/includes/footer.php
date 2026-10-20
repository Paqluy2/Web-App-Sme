</div> <!-- End main-content -->

<!-- MODAL: ADD / EDIT INVENTORY ITEM -->
<div class="modal-overlay" id="invModal">
    <div class="modal-box">
        <h3 id="invModalTitle" style="margin-bottom:14px;">Add Ingredient</h3>
        <input type="hidden" id="invId">
        <div style="margin-bottom:12px;">
            <label style="font-size:0.85rem; font-weight:600;">Ingredient Name</label>
            <input type="text" id="invName" class="form-control" placeholder="e.g. Black Tea Leaves">
        </div>
        <div style="margin-bottom:12px;">
            <label style="font-size:0.85rem; font-weight:600;">Stock Quantity</label>
            <input type="number" id="invQty" class="form-control" step="0.01" placeholder="100">
        </div>
        <div style="margin-bottom:16px;">
            <label style="font-size:0.85rem; font-weight:600;">Unit (g, ml, pcs)</label>
            <input type="text" id="invUnit" class="form-control" placeholder="grams">
        </div>
        <div style="display:flex; justify-content:flex-end; gap:8px;">
            <button class="btn btn-outline"
                onclick="document.getElementById('invModal').style.display='none'">Cancel</button>
            <button class="btn btn-primary" onclick="saveInvItem()">Save Item</button>
        </div>
    </div>
</div>

<!-- MODAL: PRINTABLE POS RECEIPT -->
<div class="modal-overlay" id="receiptModal">
    <div class="modal-box" style="max-width:350px; font-family:monospace;">
        <div id="printableReceipt">
            <h3 style="text-align:center; margin-bottom:4px;">GIGS CAFE</h3>
            <p style="text-align:center; font-size:0.8rem; margin-bottom:12px;">Official POS Receipt</p>
            <div id="receiptBody"
                style="font-size:0.85rem; border-top:1px dashed #000; border-bottom:1px dashed #000; padding:10px 0; margin-bottom:12px;">
            </div>
        </div>
        <div style="display:flex; gap:8px;">
            <button class="btn btn-outline" style="flex:1;"
                onclick="document.getElementById('receiptModal').style.display='none'; window.location.reload();">Close</button>
            <button class="btn btn-primary" style="flex:1;" onclick="window.print()">🖨️ Print</button>
        </div>
    </div>
</div>
<!-- Load Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
    // Define the base API URL
    // Points from /admin/ to /api/admin_api.php
    const adminApiUrl = '../api/admin_api.php';
    // --- Dashboard Sales Analytics Chart ---
    const salesChartDatasets = <?= json_encode($analyticsChartData) ?>;
    let salesChartInstance = null;

    function updateSalesChart(period) {
        const selected = salesChartDatasets[period];
        if (!selected) return;

        // Toggle active button style
        document.querySelectorAll('.period-btn').forEach(btn => {
            btn.className = 'btn btn-outline period-btn';
        });
        const activeBtn = document.getElementById('btn-chart-' + period);
        if (activeBtn) activeBtn.className = 'btn btn-primary period-btn';

        // Update Header Title & Period Sum
        document.getElementById('chartTitleDisplay').textContent = '📈 Sales Analytics — ' + selected.title;
        const periodTotal = selected.data.reduce((acc, val) => acc + Number(val), 0);
        document.getElementById('chartPeriodSum').textContent = 'Period Total: ₱' + periodTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const ctx = document.getElementById('salesAnalyticsCanvas');
        if (!ctx) return;

        if (salesChartInstance) {
            salesChartInstance.destroy();
        }

        salesChartInstance = new Chart(ctx, {
            type: period === 'monthly' ? 'line' : 'bar',
            data: {
                labels: selected.labels,
                datasets: [{
                    label: 'Total Revenue (₱)',
                    data: selected.data,
                    backgroundColor: period === 'monthly' ? 'rgba(29, 78, 216, 0.15)' : 'rgba(29, 78, 216, 0.82)',
                    borderColor: '#1d4ed8',
                    borderWidth: 2,
                    borderRadius: 6,
                    fill: period === 'monthly',
                    tension: 0.35,
                    pointBackgroundColor: '#1d4ed8',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return ' Revenue: ₱' + Number(context.parsed.y).toLocaleString('en-US', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // Initialize Daily Chart on Page Load
    updateSalesChart('daily');
</script>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const savedTab = sessionStorage.getItem('active_pos_tab');
        if (savedTab) {
            const tabBtn = document.querySelector(`button[onclick*="'${savedTab}'"]`);
            if (tabBtn) {
                executeTabSwitch(savedTab, tabBtn);
            }
        }
    });
    // --- Configurable Manager PIN (Default: 1234) ---
    const MANAGER_PIN = '1234';
    let pendingTabBtn = null;

    // --- Sidebar Tab Switching with Security Gate ---
    function switchTab(tabId, btn) {
        // If user clicks Inventory and hasn't unlocked it during this session
        if (tabId === 'inventory' && sessionStorage.getItem('inventory_unlocked') !== 'true') {
            pendingTabBtn = btn;
            openManagerPinModal();
            return;
        }

        executeTabSwitch(tabId, btn);
    }

    function executeTabSwitch(tabId, btn) {
        document.querySelectorAll('.section-pane').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.nav-btn').forEach(el => el.classList.remove('active'));

        const targetPane = document.getElementById('tab-' + tabId);
        if (targetPane) targetPane.classList.add('active');
        if (btn) btn.classList.add('active');

        const titles = {
            dashboard: 'Dashboard Overview',
            pos: 'Point of Sale (Walk-In Register)',
            sales: 'Sales Tracking & Online Orders',
            inventory: 'Inventory & Stock Control'
        };
        const titleEl = document.getElementById('pageTitle');
        if (titleEl) titleEl.textContent = titles[tabId] || 'Administrator';
    }

    function openManagerPinModal() {
        const modal = document.getElementById('managerPinModal');
        const input = document.getElementById('managerPinInput');
        const err = document.getElementById('pinErrorMsg');
        if (err) err.style.display = 'none';
        if (input) input.value = '';
        if (modal) modal.style.display = 'flex';
        setTimeout(() => { if (input) input.focus(); }, 100);
    }

    function cancelManagerPin() {
        const modal = document.getElementById('managerPinModal');
        if (modal) modal.style.display = 'none';
        pendingTabBtn = null;
    }

    function verifyManagerPin() {
        const input = document.getElementById('managerPinInput');
        const err = document.getElementById('pinErrorMsg');
        const enteredPin = input ? input.value.trim() : '';

        if (enteredPin === MANAGER_PIN) {
            // Store unlock status in sessionStorage (persists until browser tab is closed)
            sessionStorage.setItem('inventory_unlocked', 'true');

            const modal = document.getElementById('managerPinModal');
            if (modal) modal.style.display = 'none';

            const invBtn = pendingTabBtn || document.querySelector("button[onclick*='inventory']");
            executeTabSwitch('inventory', invBtn);
        } else {
            if (err) err.style.display = 'block';
            if (input) {
                input.value = '';
                input.focus();
            }
        }
    }

    // Optional: Call this to lock the inventory tab again
    function lockInventory() {
        sessionStorage.removeItem('inventory_unlocked');
        const posBtn = document.querySelector("button[onclick*='pos']");
        executeTabSwitch('pos', posBtn);
    }

    // --- POS Module (Preloaded directly from PHP) ---
    let allProducts = <?= json_encode($posProducts ?? [], JSON_UNESCAPED_UNICODE) ?>;
    let posCart = [];
    let activeCategory = 'All';

    function renderCategories() {
        const cats = ['All', ...new Set(allProducts.map(p => p.category))];
        const bar = document.getElementById('posCategories');
        if (!bar) return;
        bar.innerHTML = cats.map(c => `
            <button class="cat-pill ${c === activeCategory ? 'active' : ''}" onclick="setPosCategory('${c.replace(/'/g, "\\'")}')">${c}</button>
        `).join('');
    }

    function setPosCategory(cat) {
        activeCategory = cat;
        renderCategories();
        renderPosProducts();
    }

    function renderPosProducts() {
        const searchEl = document.getElementById('posSearch');
        const search = searchEl ? searchEl.value.toLowerCase() : '';
        const filtered = allProducts.filter(p => {
            const matchCat = activeCategory === 'All' || p.category === activeCategory;
            const matchSearch = p.name.toLowerCase().includes(search);
            return matchCat && matchSearch;
        });

        const grid = document.getElementById('posProductGrid');
        if (!grid) return;
        if (!filtered.length) {
            grid.innerHTML = '<p style="color:var(--text-muted);">No matching drinks found.</p>';
            return;
        }

        grid.innerHTML = filtered.map(p => `
            <div class="prod-card">
                <div>
                    <div class="cat-tag">${p.category}</div>
                    <h4>${p.name}</h4>
                </div>
                <div class="size-btns">
                    ${p.id_16oz ? `
                        <button class="size-btn" onclick="addToPosCart(${p.id_16oz}, '${p.name.replace(/'/g, "\\'")}', '16oz', ${p.price_16oz})">
                            16oz<br>₱${Number(p.price_16oz).toFixed(2)}
                        </button>
                    ` : ''}
                    ${p.id_22oz ? `
                        <button class="size-btn" onclick="addToPosCart(${p.id_22oz}, '${p.name.replace(/'/g, "\\'")}', '22oz', ${p.price_22oz})">
                            22oz<br>₱${Number(p.price_22oz).toFixed(2)}
                        </button>
                    ` : ''}
                </div>
            </div>
        `).join('');
    }

    function addToPosCart(productId, name, size, price) {
        const existing = posCart.find(i => i.product_id === productId && i.size === size);
        if (existing) {
            existing.qty++;
        } else {
            posCart.push({
                product_id: Number(productId),
                name: name,
                size: size,
                price: Number(price),
                qty: 1
            });
        }
        renderPosCart();
    }

    function changePosQty(idx, delta) {
        posCart[idx].qty += delta;
        if (posCart[idx].qty <= 0) posCart.splice(idx, 1);
        renderPosCart();
    }

    function clearPosCart() {
        posCart = [];
        renderPosCart();
    }

    function renderPosCart() {
        const list = document.getElementById('posCartList');
        if (!list) return;
        if (!posCart.length) {
            list.innerHTML = '<p style="text-align:center; color:var(--text-muted); padding:30px 0;">Select drinks from the left to build an order.</p>';
            const totalDisp = document.getElementById('posTotalDisplay');
            if (totalDisp) totalDisp.textContent = '₱0.00';
            updatePosChange();
            return;
        }

        let total = 0;
        list.innerHTML = posCart.map((item, idx) => {
            const sub = item.qty * item.price;
            total += sub;
            return `
                <div class="cart-row">
                    <div>
                        <strong>${item.name} (${item.size})</strong>
                        <div style="font-size:0.78rem; color:var(--text-muted);">₱${item.price.toFixed(2)} each</div>
                    </div>
                    <div class="qty-controls" style="display:flex; align-items:center; gap:6px;">
                        <button onclick="changePosQty(${idx}, -1)">-</button>
                        <strong>${item.qty}</strong>
                        <button onclick="changePosQty(${idx}, 1)">+</button>
                        <span style="min-width:65px; text-align:right; font-weight:700;">₱${sub.toFixed(2)}</span>
                    </div>
                </div>
            `;
        }).join('');

        const totalDisp = document.getElementById('posTotalDisplay');
        if (totalDisp) totalDisp.textContent = '₱' + total.toFixed(2);
        updatePosChange();
    }

    function getPosTotal() {
        return posCart.reduce((sum, i) => sum + (i.qty * i.price), 0);
    }

    function updatePosChange() {
        const total = getPosTotal();
        const methodEl = document.getElementById('posPaymentMethod');
        const method = methodEl ? methodEl.value : 'Cash';
        const cashEl = document.getElementById('posCashInput');
        if (!cashEl) return;

        if (method !== 'Cash') cashEl.value = total.toFixed(2);
        const cash = parseFloat(cashEl.value) || 0;
        const change = Math.max(0, cash - total);
        const changeDisp = document.getElementById('posChangeDisplay');
        if (changeDisp) changeDisp.textContent = '₱' + change.toFixed(2);
    }

    async function submitPosOrder() {
        if (!posCart.length) return alert('Please add items to the ticket first.');
        const total = getPosTotal();
        const method = document.getElementById('posPaymentMethod').value;
        const cashEl = document.getElementById('posCashInput');
        const cash = parseFloat(cashEl ? cashEl.value : 0) || 0;

        if (method === 'Cash' && cash < total) return alert('Insufficient cash tendered.');

        try {
            const res = await fetch(`${adminApiUrl}?action=process_pos`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cart: posCart, cash, payment_method: method })
            });
            const responseText = await res.text();
            let data;
            try {
                data = JSON.parse(responseText);
            } catch {
                throw new Error(`Server returned a non-JSON response (HTTP ${res.status}).`);
            }

            if (!data.success) {
                alert('Transaction Failed: ' + data.message);
                return;
            }

            // Build item rows
            const itemsHtml = data.items.map(i => `
    <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:0.85rem;">
        <span style="flex:1; text-align:left;">${i.name} (${i.size}) x${i.qty}</span>
        <span style="min-width:65px; text-align:right;">₱${(i.qty * i.price).toFixed(2)}</span>
    </div>
`).join('');

            // Modify full receipt design
            document.getElementById('receiptBody').innerHTML = `
    <!-- HEADER -->
    <div style="text-align:center; margin-bottom:12px;">
        <h3 style="margin:0; font-size:1.2rem; font-weight:800; text-transform:uppercase;">Gigs Cafe</h3>
        <p style="margin:2px 0; font-size:0.75rem; color:#475569;">Artisan Drinks & Pastries</p>
        <p style="margin:2px 0; font-size:0.75rem; color:#475569;">Rodriguez, Rizal, Philippines</p>
        <p style="margin:2px 0; font-size:0.75rem; color:#475569;">Tel: (02) 8123-4567</p>
    </div>

    <!-- TRANSACTION METADATA -->
    <div style="font-size:0.78rem; border-top:1px dashed #000; border-bottom:1px dashed #000; padding:6px 0; margin-bottom:10px;">
        <div style="display:flex; justify-content:space-between;">
            <span>Order #:</span><strong>${data.order_number}</strong>
        </div>
        <div style="display:flex; justify-content:space-between;">
            <span>Date:</span><span>${data.date}</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
            <span>Cashier:</span><span>Administrator</span>
        </div>
        <div style="display:flex; justify-content:space-between;">
            <span>Payment Method:</span><span>${data.method}</span>
        </div>
    </div>

    <!-- ITEM LIST -->
    <div style="margin-bottom:10px;">
        ${itemsHtml}
    </div>

    <!-- TOTALS & TENDER -->
    <div style="border-top:1px dashed #000; padding-top:6px; font-size:0.85rem;">
        <div style="display:flex; justify-content:space-between; font-size:1rem; font-weight:800; margin-bottom:4px;">
            <span>TOTAL:</span><span>₱${Number(data.total).toFixed(2)}</span>
        </div>
        <div style="display:flex; justify-content:space-between; color:#334155;">
            <span>Amount Tendered:</span><span>₱${Number(data.cash).toFixed(2)}</span>
        </div>
        <div style="display:flex; justify-content:space-between; color:#334155;">
            <span>Change Due:</span><span>₱${Number(data.change).toFixed(2)}</span>
        </div>
    </div>

    <!-- FOOTER MESSAGE -->
    <div style="text-align:center; margin-top:14px; padding-top:8px; border-top:1px dashed #000; font-size:0.72rem; color:#475569;">
        <p style="margin:2px 0; font-weight:600;">Thank you for dropping by!</p>
        <p style="margin:2px 0;">Wi-Fi: GigsCafe_Guest | Pass: coffee123</p>
        <p style="margin:2px 0;">Follow us on FB & IG: @gigscafe</p>
    </div>
`;
            document.getElementById('receiptModal').style.display = 'flex';
            clearPosCart();

        } catch (err) {
            alert('System Error: ' + err.message);
        }
    }

    // --- Sales & Online Orders ---
    async function updateOnlineStatus(id, status) {
        try {
            const res = await fetch(`${adminApiUrl}?action=update_online_order`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, status })
            });
            const data = await res.json();
            if (data.success) {
                // Remember that we are on the sales tab before reloading
                sessionStorage.setItem('active_pos_tab', 'sales');
                window.location.reload();
            } else {
                alert('Update Failed: ' + (data.message || 'Server rejected the update.'));
            }
        } catch (err) {
            alert('Network Error: ' + err.message);
        }
    }
    function filterSalesTable() {
        const q = document.getElementById('salesSearchInput').value.toLowerCase();
        document.querySelectorAll('#posSalesTable tbody tr').forEach(tr => {
            tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    }

    function exportSalesCSV() {
        const rows = [['Order Number', 'Items', 'Qty', 'Total', 'Method', 'Date']];
        document.querySelectorAll('#posSalesTable tbody tr').forEach(tr => {
            if (tr.style.display !== 'none') {
                const cols = Array.from(tr.querySelectorAll('td')).map(td => `"${td.innerText.replace(/"/g, '""')}"`);
                if (cols.length) rows.push(cols);
            }
        });
        const csv = rows.map(r => r.join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'gigscafe_sales_' + new Date().toISOString().slice(0, 10) + '.csv';
        a.click();
    }

    // --- Inventory (Stocks) - MATCHED TO `material_id` ---
    // --- Inventory (Stocks) with Error Alerting ---
    function filterInventoryTable() {
        const q = document.getElementById('invSearchInput').value.toLowerCase();
        document.querySelectorAll('#inventoryTable tbody tr').forEach(tr => {
            tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    }

    function openInvModal(material_id = 0, name = '', qty = 0, unit = 'grams') {
        document.getElementById('invModalTitle').textContent = material_id ? 'Edit Ingredient' : 'Add New Ingredient';
        document.getElementById('invId').value = material_id;
        document.getElementById('invName').value = name;
        document.getElementById('invQty').value = qty;
        document.getElementById('invUnit').value = unit;
        document.getElementById('invModal').style.display = 'flex';
    }

    async function saveInvItem() {
        const materialId = Number(document.getElementById('invId').value);
        const name = document.getElementById('invName').value.trim();
        const currentStock = Number(document.getElementById('invQty').value);
        const unit = document.getElementById('invUnit').value.trim();
        if (!name) return alert('Please enter an ingredient name.');
        if (!Number.isSafeInteger(materialId) || materialId < 0) return alert('Invalid inventory item ID.');
        if (!Number.isFinite(currentStock) || currentStock < 0) return alert('Please enter a valid stock quantity.');

        try {
            const res = await fetch(`${adminApiUrl}?action=save_inventory`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    material_id: materialId,
                    material_name: name,
                    current_stock: currentStock,
                    unit
                })
            });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                alert('Server Error: ' + data.message);
            }
        } catch (err) {
            alert('Network Error: ' + err.message);
        }
    }

    async function promptRestock(material_id, name) {
        const amt = prompt(`Enter quantity to add to "${name}":`, '50');
        const materialId = Number(material_id);
        const addQty = Number(amt);
        if (amt === null) return;
        if (!Number.isSafeInteger(materialId) || materialId <= 0) return alert('Invalid inventory item ID.');
        if (!Number.isFinite(addQty) || addQty <= 0) return alert('Please enter a valid positive restock quantity.');

        try {
            const res = await fetch(`${adminApiUrl}?action=restock_inventory`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ material_id: materialId, add_qty: addQty })
            });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                alert('Server Error: ' + data.message);
            }
        } catch (err) {
            alert('Network Error: ' + err.message);
        }
    }

    async function deleteInvItem(material_id) {
        const materialId = Number(material_id);
        if (!Number.isSafeInteger(materialId) || materialId <= 0) return alert('Invalid inventory item ID.');
        if (!confirm('Delete this inventory item permanently?')) return;

        try {
            const res = await fetch(`${adminApiUrl}?action=delete_inventory`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ material_id: materialId })
            });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                alert('Server Error: ' + data.message);
            }
        } catch (err) {
            alert('Network Error: ' + err.message);
        }
    }

    // Render POS categories and products immediately on load
    renderCategories();
    renderPosProducts();
</script>
</body>

</html>