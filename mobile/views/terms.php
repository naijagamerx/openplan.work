<?php
/**
 * Mobile-app legal page. Layout: views/partials/legal/mobile.php (same wording as the public page).
 */
if (!defined('MOBILE_JS_URL')) { require_once __DIR__ . '/../config.php'; }
$legalDoc = 'terms';
require VIEWS_PATH . '/partials/legal/mobile.php';
