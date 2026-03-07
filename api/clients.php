<?php
/**
 * Clients API Endpoint
 */

require_once __DIR__ . '/../config.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

if (requestMethod() !== 'GET') {
    $body = getJsonBody();
    $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::validateCsrf($token)) {
        errorResponse('Invalid CSRF token', 403);
    }
}

$db = new Database(getMasterPassword());
$id = $_GET['id'] ?? null;

switch (requestMethod()) {
    case 'GET':
        $clients = $db->load('clients');
        
        if ($id) {
            foreach ($clients as $client) {
                if ($client['id'] === $id) {
                    successResponse($client);
                }
            }
            errorResponse('Client not found', 404);
        }
        
        successResponse($clients);
        break;
        
    case 'POST':
        $body = getJsonBody();
        $action = $_GET['action'] ?? 'add';
        
        if ($action === 'update' && $id) {
            $clients = $db->load('clients');
            $found = false;
            foreach ($clients as $key => $client) {
                if ($client['id'] === $id) {
                    $allowedFields = ['name', 'email', 'phone', 'company', 'address', 'notes'];
                    foreach ($allowedFields as $field) {
                        if (isset($body[$field])) {
                            $clients[$key][$field] = $body[$field];
                        }
                    }
                    $clients[$key]['updatedAt'] = date('c');
                    $db->save('clients', $clients);
                    $found = true;
                    successResponse($clients[$key], 'Client updated');
                    break;
                }
            }
            if (!$found) errorResponse('Client not found', 404);
        } else {
            // New client
            if (empty($body['name']) || empty($body['email'])) {
                errorResponse('Name and email are required');
            }
            
            $clients = $db->load('clients');
            $newClient = [
                'id' => $db->generateId(),
                'name' => $body['name'],
                'email' => $body['email'],
                'phone' => $body['phone'] ?? '',
                'company' => $body['company'] ?? '',
                'address' => $body['address'] ?? '',
                'notes' => $body['notes'] ?? '',
                'createdAt' => date('c'),
                'updatedAt' => date('c')
            ];
            
            $clients[] = $newClient;
            $db->save('clients', $clients);
            successResponse($newClient, 'Client added');
        }
        break;
        
    case 'PUT':
        if (!$id) {
            errorResponse('Client ID required');
        }
        
        $body = getJsonBody();
        $clients = $db->load('clients');
        
        foreach ($clients as $key => $client) {
            if ($client['id'] === $id) {
                $allowedFields = ['name', 'email', 'phone', 'company', 'address', 'notes'];
                foreach ($allowedFields as $field) {
                    if (isset($body[$field])) {
                        $clients[$key][$field] = $body[$field];
                    }
                }
                $clients[$key]['updatedAt'] = date('c');
                
                $db->save('clients', $clients);
                successResponse($clients[$key], 'Client updated');
            }
        }
        
        errorResponse('Client not found', 404);
        break;
        
    case 'DELETE':
        if (!$id) {
            errorResponse('Client ID required');
        }
        
        $clients = $db->load('clients');
        $filtered = array_filter($clients, fn($c) => $c['id'] !== $id);
        
        if (count($filtered) === count($clients)) {
            errorResponse('Client not found', 404);
        }
        
        $db->save('clients', array_values($filtered));
        successResponse(null, 'Client deleted');
        break;
        
    default:
        errorResponse('Method not allowed', 405);
}
