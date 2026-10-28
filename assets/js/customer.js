'use strict';

document.addEventListener('DOMContentLoaded', () => {
    let cart = JSON.parse(localStorage.getItem('gigs_cafe_cart')) || [];
    const DELIVERY_FEE = 50.00;

    // DOM References
    const cartCountBadge = document.getElementById('cartCountBadge');
    const openCartBtn = document.getElementById('openCartBtn');
    const closeCartBtn = document.getElementById('closeCartBtn');
    const cartDrawer = document.getElementById('cartDrawer');
    const cartOverlay = document.getElementById('cartOverlay');
    const cartItemsList = document.getElementById('cartItemsList');
    const drawerSubtotal = document.getElementById('drawerSubtotal');
    const drawerDelivery = document.getElementById('drawerDelivery');
    const drawerTotal = document.getElementById('drawerTotal');
    const btnProceedCheckout = document.getElementById('btnProceedCheckout');
    const authModal = document.getElementById('authModal');

    // 1. Drawer open/close
    function toggleCart(isOpen) {
        cartDrawer.classList.toggle('open', isOpen);
        cartOverlay.classList.toggle('open', isOpen);
    }

    if (openCartBtn) openCartBtn.addEventListener('click', () => toggleCart(true));
    if (closeCartBtn) closeCartBtn.addEventListener('click', () => toggleCart(false));
    if (cartOverlay) cartOverlay.addEventListener('click', () => toggleCart(false));

    // 2. Add to Cart from Menu
    document.querySelectorAll('.btn-add-cart').forEach(button => {
        button.addEventListener('click', () => {
            const id = parseInt(button.dataset.id, 10);
            const name = button.dataset.name;
            const price = parseFloat(button.dataset.price);

            const existing = cart.find(item => item.product_id === id);
            if (existing) {
                existing.qty++;
            } else {
                cart.push({ product_id: id, name, price, qty: 1 });
            }
            saveCart();
            renderCart();
            toggleCart(true);
        });
    });

    // 3. Render Cart & Calculate
    function saveCart() {
        localStorage.setItem('gigs_cafe_cart', JSON.stringify(cart));
    }

    function renderCart() {
        if (!cartItemsList) return;
        cartItemsList.innerHTML = '';

        const totalQty = cart.reduce((acc, item) => acc + item.qty, 0);
        if (cartCountBadge) cartCountBadge.textContent = totalQty.toString();

        if (cart.length === 0) {
            cartItemsList.innerHTML = '<p style="text-align:center; color:#7a6e67; margin-top:40px;">Your cart is empty.</p>';
            if (drawerSubtotal) drawerSubtotal.textContent = '₱0.00';
            if (drawerDelivery) drawerDelivery.textContent = '₱0.00';
            if (drawerTotal) drawerTotal.textContent = '₱0.00';
            return;
        }

        let subtotal = 0;
        cart.forEach((item, index) => {
            const line = item.price * item.qty;
            subtotal += line;

            const div = document.createElement('div');
            div.style.cssText = 'display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e8ded4; padding:10px 0;';
            div.innerHTML = `
                <div>
                    <strong>${item.name}</strong>
                    <div style="font-size:0.85rem; color:#7a6e67;">₱${item.price.toFixed(2)} x ${item.qty}</div>
                </div>
                <div style="display:flex; align-items:center; gap:6px;">
                    <button type="button" class="btn-qty" onclick="updateCartItem(${index}, -1)">-</button>
                    <span style="font-weight:700;">${item.qty}</span>
                    <button type="button" class="btn-qty" onclick="updateCartItem(${index}, 1)">+</button>
                    <button type="button" class="btn-del" onclick="removeCartItem(${index})">&times;</button>
                </div>
            `;
            cartItemsList.appendChild(div);
        });

        const isDelivery = document.querySelector('input[name="cust_fulfillment"]:checked')?.value === 'Delivery';
        const fee = isDelivery ? DELIVERY_FEE : 0.00;
        const total = subtotal + fee;

        if (drawerSubtotal) drawerSubtotal.textContent = `₱${subtotal.toFixed(2)}`;
        if (drawerDelivery) drawerDelivery.textContent = `₱${fee.toFixed(2)}`;
        if (drawerTotal) drawerTotal.textContent = `₱${total.toFixed(2)}`;
    }

    window.updateCartItem = function (index, delta) {
        cart[index].qty += delta;
        if (cart[index].qty <= 0) cart.splice(index, 1);
        saveCart();
        renderCart();
    };

    window.removeCartItem = function (index) {
        cart.splice(index, 1);
        saveCart();
        renderCart();
    };

    document.querySelectorAll('input[name="cust_fulfillment"]').forEach(r => {
        r.addEventListener('change', renderCart);
    });

    // 4. Modal Triggers
window.openAuthModal = function (notice = '') {
    const modal = document.getElementById('authModal');
    const noticeEl = document.getElementById('authNotice');
    
    // 1. Set any error messages (like "Please sign in to complete your checkout.")
    if (noticeEl) noticeEl.textContent = notice;
    
    // 2. Open the login modal
    if (modal) modal.style.display = 'flex';
    showLoginForm();
    
    // 3. FORCE THE CART TO CLOSE automatically so they don't overlap
    const cartDrawer = document.getElementById('cartDrawer');
    const cartOverlay = document.getElementById('cartOverlay');
    if (cartDrawer) cartDrawer.classList.remove('open');
    if (cartOverlay) cartOverlay.classList.remove('open');
};

    window.closeAuthModal = function () {
        authModal.style.display = 'none';
    };

    window.toggleAuthForms = function (showRegister) {
        document.getElementById('customerLoginForm').style.display = showRegister ? 'none' : 'block';
        document.getElementById('customerRegisterForm').style.display = showRegister ? 'block' : 'none';
        document.getElementById('authModalTitle').textContent = showRegister ? 'Register Account' : 'Welcome to Gigs Cafe';
    };

   // 5. Checkout Execution
    let isCheckingOut = false; // 1. Create a strict lock variable

    if (btnProceedCheckout) {
        btnProceedCheckout.addEventListener('click', async () => {
            // 2. If already processing, kill the duplicate click instantly
            if (isCheckingOut) return; 
            
            if (cart.length === 0) return alert('Your cart is empty.');

            // 3. Lock the function so no other clicks can get through
            isCheckingOut = true; 
            btnProceedCheckout.disabled = true;
            btnProceedCheckout.textContent = 'Submitting Order...';

            const fulfillment = document.querySelector('input[name="cust_fulfillment"]:checked').value;

            try {
                const response = await fetch('api/customer_api.php?action=checkout', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        items: cart.map(i => ({ product_id: i.product_id, qty: i.qty })),
                        fulfillment_type: fulfillment
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert(`Order Placed Successfully!\n\nOrder Code: ${result.transaction_code}`);
                    cart = [];
                    saveCart();
                    renderCart();
                    toggleCart(false);
                    // Do NOT unlock if successful, let the page redirect
                    window.location.href = 'orders.php';
                } else {
                    alert(`Order Error: ${result.message}`);
                    isCheckingOut = false; // Unlock only if there was an error
                    btnProceedCheckout.disabled = false;
                    btnProceedCheckout.textContent = 'Place Online Order';
                }
            } catch (err) {
                alert('Connection failure when processing order.');
                isCheckingOut = false; // Unlock if the connection fails
                btnProceedCheckout.disabled = false;
                btnProceedCheckout.textContent = 'Place Online Order';
            }
        });
    }

    // 6. Category Filtering on Menu Page
    const catPills = document.querySelectorAll('.category-pill');
    const productCards = document.querySelectorAll('.customer-product-card');

    catPills.forEach(pill => {
        pill.addEventListener('click', () => {
            catPills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');

            const selectedCat = pill.dataset.cat;
            productCards.forEach(card => {
                const itemCat = card.dataset.category;
                card.style.display = (selectedCat === 'all' || itemCat === selectedCat) ? 'flex' : 'none';
            });
        });
    });

    renderCart();
});
// --- OTP Registration Controllers ---

window.showLoginForm = function () {
    document.getElementById('customerLoginForm').style.display = 'block';
    document.getElementById('otpStep1Form').style.display = 'none';
    document.getElementById('otpStep2Form').style.display = 'none';
    document.getElementById('authModalTitle').textContent = 'Sign In';
};

window.showRegisterStep1 = function () {
    document.getElementById('customerLoginForm').style.display = 'none';
    document.getElementById('otpStep1Form').style.display = 'block';
    document.getElementById('otpStep2Form').style.display = 'none';
    document.getElementById('authModalTitle').textContent = 'Register with Email OTP';
};

window.showRegisterStep2 = function (email) {
    document.getElementById('customerLoginForm').style.display = 'none';
    document.getElementById('otpStep1Form').style.display = 'none';
    document.getElementById('otpStep2Form').style.display = 'block';
    document.getElementById('displayTargetEmail').textContent = email;
    document.getElementById('authModalTitle').textContent = 'Enter Verification Code';
    document.getElementById('reg_otp').focus();
};

// --- Modal Form Toggling ---

// --- Modal Form Toggling ---
window.showLoginForm = function () {
    const loginForm = document.getElementById('customerLoginForm');
    const registerForm = document.getElementById('customerRegisterForm');
    const title = document.getElementById('authModalTitle');

    if (loginForm) loginForm.style.display = 'block';
    if (registerForm) registerForm.style.display = 'none';
    if (title) title.textContent = 'Sign In';
};

window.showRegisterForm = function () {
    const loginForm = document.getElementById('customerLoginForm');
    const registerForm = document.getElementById('customerRegisterForm');
    const title = document.getElementById('authModalTitle');

    if (loginForm) loginForm.style.display = 'none';
    if (registerForm) registerForm.style.display = 'block';
    if (title) title.textContent = 'Create an Account';
};

window.showRegisterStep1 = window.showRegisterForm;

window.openAuthModal = function (notice = '') {
    const modal = document.getElementById('authModal');
    const noticeEl = document.getElementById('authNotice');
    if (noticeEl) noticeEl.textContent = notice;
    if (modal) modal.style.display = 'flex';
    showLoginForm();
};

window.closeAuthModal = function () {
    const modal = document.getElementById('authModal');
    if (modal) modal.style.display = 'none';
};