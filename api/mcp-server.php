<?php
/**
 * MCP-over-HTTP Server Endpoint
 *
 * Speaks Model Context Protocol JSON-RPC 2.0 over HTTPS. Lets remote MCP
 * clients (Hermes, hosted agents, mobile clients) reach this server without
 * requiring a local stdio Node bridge.
 *
 * Authentication: X-MCP-Api-Token + X-Master-Password headers.
 * Protocol spec: https://spec.modelcontextprotocol.io (2025-06-18)
 *
 * Methods supported:
 *   - initialize       Handshake. Negotiates protocol version + capabilities.
 *   - tools/list       Returns the registered tool catalog.
 *   - tools/call       Executes one tool by name with the given arguments.
 *
 * Note: api/mcp.php is the user-facing token management endpoint (UI-only,
 * session-authenticated). This file is the MCP protocol surface itself.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/MCPServer.php';

header('Content-Type: application/json');

// $rpcId is intentionally untyped: per JSON-RPC 2.0 the request id may be a
// string, an integer, or null, and the response MUST echo it back with the
// SAME type. Forcing ?int coerced string ids (e.g. "1" -> 1) causing a
// type mismatch that strict clients (Hermes) reject, and threw a TypeError
// on non-numeric string ids.
$emit = function($rpcId, array $payload, int $httpStatus = 200) {
    http_response_code($httpStatus);
    echo json_encode(['jsonrpc' => '2.0', 'id' => $rpcId] + $payload);
    exit;
};

if (requestMethod() !== 'POST') {
    $emit(null, ['error' => ['code' => -32600, 'message' => 'MCP requires POST']], 405);
}

$body = getJsonBody();
$rpcId = $body['id'] ?? null;
$method = $body['method'] ?? '';
$params = $body['params'] ?? [];

if ($method === '') {
    $emit($rpcId, ['error' => ['code' => -32600, 'message' => 'Missing method']], 400);
}

if (!Auth::isMcp()) {
    $emit($rpcId, ['error' => ['code' => -32001, 'message' => 'Unauthorized — invalid or missing X-MCP-Api-Token']], 401);
}

try {
    $db = new Database(getMasterPassword(), Auth::userId());
    $db->load('users', true);
} catch (Throwable $e) {
    $emit($rpcId, ['error' => ['code' => -32001, 'message' => 'Database decryption failed — wrong master password']], 401);
}

$server = new MCPServer($db);

try {
    $result = match ($method) {
        'initialize'                => $server->initialize($params),
        'notifications/initialized' => null,
        'tools/list'                => $server->listTools(),
        'tools/call'                => $server->callTool($params),
        default                     => ['error' => ['code' => -32601, 'message' => "Method not found: {$method}"]],
    };
} catch (Throwable $e) {
    error_log('[mcp-server] handler exception: ' . $e->getMessage());
    $emit($rpcId, ['error' => ['code' => -32603, 'message' => 'Internal error: ' . $e->getMessage()]], 500);
}

if ($result === null) {
    http_response_code(204);
    exit;
}

$emit($rpcId, $result);
