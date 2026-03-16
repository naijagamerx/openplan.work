<?php
/**
 * Clear Device Cookie - Run this to fix stuck mobile/desktop view
 * Access: http://localhost:4041/clear-device-cookie.php
 */

// Clear the device preference cookie
setcookie('lazyman_device_preference', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => false,
    'httponly' => false,
    'samesite' => 'Lax'
]);

// Also clear via standard method
setcookie('lazyman_device_preference', '', time() - 3600, '/');

header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Device Cookie Cleared</title>
    <style>
        body { font-family: sans-serif; padding: 40px; text-align: center; }
        .success { color: green; }
        a { display: inline-block; margin: 10px; padding: 10px 20px; background: #000; color: #fff; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <h1 class="success">✓ Device Cookie Cleared</h1>
    <p>The device preference cookie has been cleared.</p>
    <p>You should now see the correct version (desktop on PC, mobile on phones).</p>

    <div style="margin-top: 30px;">
        <a href="/?page=login">Go to Desktop Login</a>
        <a href="/mobile/?page=login">Go to Mobile Login</a>
    </div>

    <script>
        // Also clear via JavaScript
        document.cookie = 'lazyman_device_preference=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    </script>
</body>
</html>
