<?php
// 1. Get the IP address of the current visitor
$visitor_ip = $_SERVER['REMOTE_ADDR'];

// 2. The Blacklist: Add the IPs of attackers here, wrapped in quotes and separated by commas
$banned_ips = [
    '192.168.1.100', // Example fake IP
    '203.0.113.45'   // Another example
];

// 3. If their IP is in the blacklist array, kill the page instantly
if (in_array($visitor_ip, $banned_ips)) {
    // Send a 403 Forbidden status code to the browser
    http_response_code(403);
    
    // Stop all PHP execution and show a red error screen
    die("<html>
        <body style='background-color: #fee2e2; color: #991b1b; text-align: center; padding: 50px; font-family: Arial, sans-serif;'>
            <h2>Access Denied</h2>
            <p>Your IP address (<strong>" . htmlspecialchars($visitor_ip) . "</strong>) has been permanently blocked due to suspicious activity or policy violations.</p>
        </body>
    </html>");
}
?>