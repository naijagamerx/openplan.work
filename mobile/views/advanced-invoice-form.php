<?php
/**
 * Mobile Advanced Invoice Form — falls through to the simple invoice form on
 * mobile. The desktop "advanced" variant has dense multi-pane editing that
 * doesn't translate well to a 420px viewport; routing here keeps deep links
 * working without serving a broken layout.
 */

if (!Auth::check()) { header('Location: ?page=login'); exit; }

$qs = $_SERVER['QUERY_STRING'] ?? '';
$forward = '?page=invoice-form' . ($qs ? '&' . preg_replace('/^page=advanced-invoice-form&?/', '', $qs) : '');
header('Location: ' . rtrim($forward, '&'));
exit;
