'use strict';

document.addEventListener('DOMContentLoaded', () => {
    let cart = [];
    const TAX_RATE = 0.08;
    const DELIVERY_FEE = 50.00;

    const cartCountEl = document.getElementById('cartCount');
    const cartContainer = document.getElementById('cartItemsContainer');
    const txtSubtotal = document.getElementById('txtSubtotal');
    const txtTax = document.getElementById('txtTax');
    const txtFulfillment = document.getElementById('txtFulfillment');
    const txtGrandTotal = document.getElementById('txtGrandTotal');
    const orderForm = document.getElementById('orderForm');
    const deliveryFields = document.getElementById('deliveryFields');
    const pickupFields = document.getElementById('pickupFields');

    // Add to Cart
    document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = parseInt(btn.dataset.id, 10);
            const name = btn.dataset.name;
            const price = parseFloat(btn.dataset.price);

            const existing = cart.find(i => i.product_id === id);
            if (existing) {
                existing.qty++;
            } else {
                cart.push({ product_id: id, name, price, qty: 1 });
            }
            renderCart();
        });
    });

    // Toggle Fulfillment Options
    document.querySelectorAll('input[name="fulfillment_type"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
            if (e.target.value === 'Delivery') {
                deliveryFields.style.display = 'block';
                pickupFields.style.display = 'none';
            } else {
                deliveryFields.style.display = 'none';
                pickupFields.style.display = 'block';
            }
            renderCart();
        });
    });

    function renderCart() {
        cartContainer.innerHTML = '';
        if (cart.length === 0) {
            cartContainer.innerHTML = '<p class="empty-msg">Your shopping cart is empty.</p>';
            cartCountEl.textContent = '0';
            updateTotals(0);
            return;
        }

        let totalItems = 0;
        let subtotal = 0;

        cart.forEach((item, index) => {
            totalItems += item.qty;
            const lineTotal = item.price * item.qty;
            subtotal += lineTotal;

            const div = document.createElement('div');
            div.className = 'cart-item-row';
            div.innerHTML = `
                <div class="details">
                    <strong>${item.name}</strong>
                    <div>₱${item.price.toFixed(2)} x ${item.qty} = ₱${lineTotal.toFixed(2)}</div>
                </div>
                <div class="actions">
                    <button type="button" class="btn-qty" data-action="decrease" data-idx="${index}">-</button>
                    <span class="qty">${item.qty}</span>
                    <button type="button" class="btn-qty" data-action="increase" data-idx="${index}">+</button>
                    <button type="button" class="btn-del" data-action="remove" data-idx="${index}">&times;</button>
                </div>
            `;
            cartContainer.appendChild(div);
        });

        cartCountEl.textContent = totalItems.toString();
        updateTotals(subtotal);
        bindCartButtons();
    }

    function updateTotals(subtotal) {
        const isDelivery = document.querySelector('input[name="fulfillment_type"]:checked').value === 'Delivery';
        const fulfillmentFee = isDelivery ? DELIVERY_FEE : 0.00;
        const tax = subtotal * TAX_RATE;
        const grandTotal = subtotal + tax + fulfillmentFee;

        txtSubtotal.textContent = `₱${subtotal.toFixed(2)}`;
        txtTax.textContent = `₱${tax.toFixed(2)}`;
        txtFulfillment.textContent = `₱${fulfillmentFee.toFixed(2)}`;
        txtGrandTotal.textContent = `₱${grandTotal.toFixed(2)}`;
    }

    function bindCartButtons() {
        document.querySelectorAll('.btn-qty').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const idx = parseInt(e.target.dataset.idx, 10);
                const action = e.target.dataset.action;
                if (action === 'increase') {
                    cart[idx].qty++;
                } else if (action === 'decrease') {
                    cart[idx].qty--;
                    if (cart[idx].qty <= 0) cart.splice(idx, 1);
                }
                renderCart();
            });
        });

        document.querySelectorAll('.btn-del').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const idx = parseInt(e.target.dataset.idx, 10);
                cart.splice(idx, 1);
                renderCart();
            });
        });
    }

    // Process Checkout
    orderForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (cart.length === 0) {
            alert('Your cart is empty.');
            return;
        }

        const fulfillmentType = document.querySelector('input[name="fulfillment_type"]:checked').value;
        const payload = {
            items: cart.map(i => ({ product_id: i.product_id, qty: i.qty })),
            fulfillment: {
                type: fulfillmentType,
                address: fulfillmentType === 'Delivery' ? document.getElementById('deliveryAddress').value.trim() : null,
                slot: fulfillmentType === 'Pickup' ? document.getElementById('pickupSlot').value : null
            },
            customer: {
                name: document.getElementById('custName').value.trim(),
                email: document.getElementById('custEmail').value.trim(),
                phone: document.getElementById('custPhone').value.trim()
            }
        };

        if (fulfillmentType === 'Delivery' && !payload.fulfillment.address) {
            alert('Please provide your complete delivery address.');
            return;
        }

        try {
            const res = await fetch('api/checkout.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (data.success) {
                alert(`Order Placed Successfully!\nReference Code: ${data.order_id}`);
                cart = [];
                renderCart();
                orderForm.reset();
            } else {
                alert(`Order Failed: ${data.message}`);
            }
        } catch (err) {
            alert('Error processing checkout. Please check server connection.');
        }
    });

    // Real-Time Search & Category Filters
    const searchInput = document.getElementById('storeSearch');
    const catFilter = document.getElementById('catFilter');

    function filterProducts() {
        const query = searchInput.value.toLowerCase();
        const cat = catFilter.value;

        document.querySelectorAll('.product-card').forEach(card => {
            const title = card.querySelector('h3').textContent.toLowerCase();
            const category = card.dataset.category;
            const matchesSearch = title.includes(query);
            const matchesCat = !cat || category === cat;

            card.style.display = (matchesSearch && matchesCat) ? 'flex' : 'none';
        });
    }

    searchInput.addEventListener('input', filterProducts);
    catFilter.addEventListener('change', filterProducts);
});