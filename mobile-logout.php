<?php
/**
 * Quick Mobile Logout Script
 * Run this file to logout mobile session
 */

session_start();

// Destroy all session data
$_SESSION = [];

// Delete the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destroy the session
session_destroy();

?>
<!DOCTYPE html>
<html>
<head>
    <titleLogged Out</title>
    <style>
        body {
            font-family: system-ui, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f5f5;
        }
        .box {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        h1 { color: #000; margin-bottom: 20px; }
        a {
            display: inline-block;
            background: #000;
            color: #fff;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
        }
        a:hover { opacity: 0.9; }
        .deleted { color: #22c55e; font-size: 14px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>👋 Logged Out!</h1>
        <p class="deleted">Session destroyed successfully.</p>
        <a href="?page=login&device=mobile">Sign In Again</a>
        <br><br>
        <a href="?page=dashboard&device=mobile" style="background: #666;">Go to Dashboard</a>
    </div>
</body>
</html>
