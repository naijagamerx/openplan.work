<?php
/**
 * API Router
 * 
 * This file handles routing of API requests to the appropriate endpoint files.
 * It's placed in the api/ directory so that requests to /api/ are routed here.
 */

// Get the requested API file
$requestUri = $_SERVER['REQUEST_URI'];
$requestUri = parse_url($requestUri, PHP_URL_PATH);

// Extract the API file name from the request
// /api/auth.php -> auth.php
// /api/auth -> auth.php
$pathParts = explode('/', trim($requestUri, '/'));

if (count($pathParts) < 2 || $pathParts[0] !== 'api') {
    http_response_code(404);
    echo json_encode(['error' => 'Not Found']);
    exit;
}

$apiFile = $pathParts[1];
// Remove .php extension if present
$apiFile = preg_replace('/\.php$/', '', $apiFile);

// Prevent directory traversal
if (strpos($apiFile, '..') !== false || strpos($apiFile, '/') !== false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$apiPath = __DIR__ . '/' . $apiFile . '.php';

// Prevent recursive self-include when requesting /api/index.php.
if ($apiFile === 'index') {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'API router is running',
        'hint' => 'Call concrete endpoints like /api/health.php or /api/auth.php?action=status'
    ]);
    exit;
}

// Check if the file exists
if (!file_exists($apiPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    exit;
}

// Include the API file
require $apiPath;
