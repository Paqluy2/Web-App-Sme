<!-- 2. Global Footer -->
<footer style="background: var(--cafe-espresso); color: #e2e8f0; padding: 40px 20px; margin-top: 60px;">
    <div style="max-width: 1200px; margin: 0 auto; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 30px;">
        <div>
            <h3 style="color:#fef08a; margin-bottom:10px;">☕ Gigs Cafe</h3>
            <p style="max-width:320px; font-size:0.9rem; color:#cbd5e1;">Freshly brewed coffee, signature iced blends, artisan milk teas, and fruit tea infusions crafted daily.</p>
        </div>
        <div>
            <h4 style="margin-bottom:10px; color:#ffffff;">Customer Service</h4>
            <ul style="list-style:none; padding:0; font-size:0.85rem; line-height:1.8;">
                <li><a href="menu.php" style="color:#cbd5e1; text-decoration:none;">Online Ordering</a></li>
                <li><a href="orders.php" style="color:#cbd5e1; text-decoration:none;">Track Existing Orders</a></li>
                <li><a href="account.php" style="color:#cbd5e1; text-decoration:none;">Profile & Saved Addresses</a></li>
            </ul>
        </div>
        <div>
            <h4 style="margin-bottom:10px; color:#ffffff;">Hours & Store Pickup</h4>
            <p style="font-size:0.85rem; color:#cbd5e1;">Monday – Sunday: 8:00 AM – 10:00 PM<br>Fast local delivery & pick-up available.</p>
        </div>
    </div>
    <div style="text-align:center; border-top:1px solid #332722; margin-top:30px; padding-top:20px; font-size:0.8rem; color:#94a3b8;">
        &copy; <?= date('Y') ?> Gigs Cafe. All Rights Reserved.
    </div>
</footer>

<!-- 3. Global Scripts -->
<script src="assets/js/customer.js?v=4.0"></script>
<script src="https://unpkg.com/lucide@latest"></script>
</body>
</html>