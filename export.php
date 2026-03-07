<?php
require_once __DIR__ . '/config.php';

if (!Auth::check()) {
    header('Location: ' . APP_URL . '?page=login&reason=session_expired');
    exit;
}

if (!Auth::isAdmin()) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f7f7f7; color: #111; padding: 40px; }
            .box { max-width: 640px; margin: 40px auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 24px; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>Administrator access required</h1>
            <p>Only administrators can generate release exports.</p>
            <p><a href="<?php echo e(APP_URL . '?page=dashboard'); ?>">Back to dashboard</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

header('Location: ' . APP_URL . '?page=release-export');
exit;
