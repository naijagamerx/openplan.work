<?php
/**
 * Mobile Advanced Invoice View (Modern variant) — redirects to the standard
 * advanced-invoice-view on mobile. The "modern" variant is a desktop-only
 * layout experiment; mobile uses the regular view which has the same data
 * surfaced more compactly.
 */

if (!Auth::check()) { header('Location: ?page=login'); exit; }

$qs = $_SERVER['QUERY_STRING'] ?? '';
$forward = '?page=advanced-invoice-view' . ($qs ? '&' . preg_replace('/^page=advanced-invoice-view-modern&?/', '', $qs) : '');
header('Location: ' . rtrim($forward, '&'));
exit;
