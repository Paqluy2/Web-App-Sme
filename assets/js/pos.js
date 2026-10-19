'use strict';

document.addEventListener('DOMContentLoaded', () => {
    let posItems = [];
    const TAX_RATE = 0.08;

    // DOM Elements
    const tapeBody = document.getElementById('posTapeBody');
    const subtotalEl = document.getElementById('posSubtotal');
    const taxEl = document.getElementById('posTax');
    const grandTotalEl = document.getElementById('posGrandTotal');
    const tenderedInput = document.getElementById('tenderedAmount');
    const changeEl = document.getElementById('posChange');
    const paymentMethodSelect = document.getElementById('paymentMethod');
    const tenderGroup = document.getElementById('tenderGroup');
    const btnComplete = document.getElementById('btnCompleteSale');
    const filterInput = document.getElementById('posFilter');
    const pillButtons = document.querySelectorAll('.pill-btn');
    const cards = document.querySelectorAll('.pos-item-card');

    // 1. Add Item to Active Ticket Tape
    cards.forEach(card => {
        card.addEventListener('click', () => {
            const id = parseInt(card.dataset.id, 10);
            const name = card.dataset.name;
            const price = parseFloat(card.dataset.price);

            const existing = posItems.find(item => item.product_id === id);
            if (existing) {
                existing.qty++;
            } else {
                posItems.push({ product_id: id, name, price, qty: 1 });
            }
            renderTape();
        });
    });

    // 2. Render Active Ticket and Calculate Totals
    function renderTape() {
        tapeBody.innerHTML = '';

        if (posItems.length === 0) {
            tapeBody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; color: #94a3b8; padding: 24px 0;">
                        No items selected.
                    </td>
                </tr>`;
            subtotalEl.textContent = '₱0.00';
            taxEl.textContent = '₱0.00';
            grandTotalEl.textContent = '₱0.00';
            calculateChange(0);
            return;
        }

        let total = 0;
        posItems.forEach((item, index) => {
            const line = item.price * item.qty;
            total += line;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${item.name}</strong></td>
                <td>
                    <div class="qty-stepper">
                        <button type="button" class="btn-qty" data-action="decrease" data-idx="${index}">-</button>
                        <span class="qty-display">${item.qty}</span>
                        <button type="button" class="btn-qty" data-action="increase" data-idx="${index}">+</button>
                    </div>
                </td>
                <td>₱${item.price.toFixed(2)}</td>
                <td><strong>₱${line.toFixed(2)}</strong></td>
                <td>
                    <button type="button" class="btn-del" data-action="remove" data-idx="${index}">&times;</button>
                </td>
            `;
            tapeBody.appendChild(tr);
        });

        const tax = total * TAX_RATE;
        subtotalEl.textContent = `₱${(total - tax).toFixed(2)}`;
        taxEl.textContent = `₱${tax.toFixed(2)}`;
        grandTotalEl.textContent = `₱${total.toFixed(2)}`;

        bindTapeButtons();
        calculateChange(total);
    }

    // 3. Attach Stepper and Delete Events
    function bindTapeButtons() {
        tapeBody.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.dataset.idx, 10);
                const action = btn.dataset.action;

                if (action === 'increase') {
                    posItems[idx].qty++;
                } else if (action === 'decrease') {
                    posItems[idx].qty--;
                    if (posItems[idx].qty <= 0) {
                        posItems.splice(idx, 1);
                    }
                } else if (action === 'remove') {
                    posItems.splice(idx, 1);
                }
                renderTape();
            });
        });
    }

    // 4. Change Calculation Engine
    function calculateChange(grandTotal) {
        if (paymentMethodSelect.value !== 'Cash') {
            changeEl.textContent = '₱0.00';
            changeEl.style.color = '#10b981';
            return;
        }

        const tendered = parseFloat(tenderedInput.value) || 0;
        const change = tendered - grandTotal;

        if (change >= 0) {
            changeEl.style.color = '#10b981';
            changeEl.textContent = `₱${change.toFixed(2)}`;
        } else {
            changeEl.style.color = '#ef4444';
            changeEl.textContent = `-₱${Math.abs(change).toFixed(2)}`;
        }
    }

    tenderedInput.addEventListener('input', () => {
        const total = posItems.reduce((acc, i) => acc + (i.price * i.qty), 0);
        calculateChange(total);
    });

    // 5. Quick-Tender Cash Buttons
    document.querySelectorAll('.quick-cash-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const total = posItems.reduce((acc, i) => acc + (i.price * i.qty), 0);
            const val = btn.dataset.val;

            if (val === 'exact') {
                tenderedInput.value = total > 0 ? total.toFixed(2) : '';
            } else {
                tenderedInput.value = parseFloat(val).toFixed(2);
            }
            calculateChange(total);
        });
    });

    // 6. Payment Method Toggle
    paymentMethodSelect.addEventListener('change', (e) => {
        tenderGroup.style.display = (e.target.value === 'Cash') ? 'block' : 'none';
        const total = posItems.reduce((acc, i) => acc + (i.price * i.qty), 0);
        calculateChange(total);
    });

    // 7. Filtering (Search input + Category buttons)
    function filterProducts() {
        const query = filterInput.value.toLowerCase().trim();
        const activeCategory = document.querySelector('.pill-btn.active').dataset.cat;

        cards.forEach(card => {
            const name = card.dataset.name.toLowerCase();
            const type = card.dataset.type.toLowerCase();
            const matchesQuery = name.includes(query) || type.includes(query);
            const matchesCategory = (activeCategory === 'all') || (card.dataset.type === activeCategory);

            card.style.display = (matchesQuery && matchesCategory) ? 'flex' : 'none';
        });
    }

    filterInput.addEventListener('input', filterProducts);

    pillButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            pillButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            filterProducts();
        });
    });

    // 8. Submit Order to API
    btnComplete.addEventListener('click', async () => {
        if (posItems.length === 0) {
            alert('Cannot finalize a sale with an empty ticket.');
            return;
        }

        const total = posItems.reduce((acc, i) => acc + (i.price * i.qty), 0);
        const method = paymentMethodSelect.value;
        const tendered = method === 'Cash' ? (parseFloat(tenderedInput.value) || 0) : total;

        if (method === 'Cash' && tendered < total) {
            alert(`Insufficient cash tendered. Total amount is ₱${total.toFixed(2)}.`);
            return;
        }

        btnComplete.disabled = true;
        btnComplete.textContent = 'Processing Sale...';

        try {
            const response = await fetch('../api/pos_transaction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    items: posItems.map(i => ({ product_id: i.product_id, qty: i.qty })),
                    payment_method: method,
                    amount_tendered: tendered
                })
            });

            const data = await response.json();

            if (data.success) {
                alert(`Transaction Complete!\n\nReceipt: ${data.transaction_code}\nTotal: ₱${data.grand_total.toFixed(2)}\nChange: ₱${data.change.toFixed(2)}`);
                posItems = [];
                tenderedInput.value = '';
                renderTape();
            } else {
                alert(`Transaction Error: ${data.message}`);
            }
        } catch (err) {
            alert('Network error: Unable to connect to the transaction API endpoint.');
        } finally {
            btnComplete.disabled = false;
            btnComplete.textContent = 'Finalize Sale';
        }
    });

    // Initialize
    renderTape();
});