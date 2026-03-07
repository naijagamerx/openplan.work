<?php
/**
 * Inventory API Endpoint
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
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;

switch (requestMethod()) {
    case 'GET':
        $inventory = $db->load('inventory');
        successResponse($inventory);
        break;
        
    case 'POST':
        $body = getJsonBody();
        
        // Stock adjustment
        if ($action === 'adjust') {
            $inventory = $db->load('inventory');
            $productId = $body['id'] ?? '';
            $adjustment = intval($body['adjustment'] ?? 0);
            
            foreach ($inventory as $key => $product) {
                if ($product['id'] === $productId) {
                    $inventory[$key]['quantity'] = max(0, ($product['quantity'] ?? 0) + $adjustment);
                    $db->save('inventory', $inventory);
                    successResponse($inventory[$key], 'Stock updated');
                    break;
                }
            }
            errorResponse('Product not found', 404);
        }

        // Add or Update product
        if (empty($body['name'])) {
            errorResponse('Product name is required');
        }
        
        $inventory = $db->load('inventory');
        
        if ($action === 'update' && $id) {
            $found = false;
            foreach ($inventory as $key => $product) {
                if ($product['id'] === $id) {
                    $inventory[$key]['sku'] = $body['sku'] ?? $product['sku'];
                    $inventory[$key]['name'] = $body['name'] ?? $product['name'];
                    $inventory[$key]['description'] = $body['description'] ?? $product['description'];
                    $inventory[$key]['category'] = $body['category'] ?? $product['category'];
                    $inventory[$key]['quantity'] = intval($body['quantity'] ?? ($product['quantity'] ?? 0));
                    $inventory[$key]['unitPrice'] = floatval($body['unitPrice'] ?? ($product['unitPrice'] ?? 0));
                    $inventory[$key]['costPrice'] = floatval($body['costPrice'] ?? ($product['costPrice'] ?? 0));
                    $inventory[$key]['reorderPoint'] = intval($body['reorderPoint'] ?? ($product['reorderPoint'] ?? 5));
                    $inventory[$key]['updatedAt'] = date('c');
                    
                    $db->save('inventory', $inventory);
                    $found = true;
                    successResponse($inventory[$key], 'Product updated');
                    break;
                }
            }
            if (!$found) errorResponse('Product not found', 404);
        } else {
            // New product
            $newProduct = [
                'id' => $db->generateId(),
                'sku' => $body['sku'] ?? '',
                'name' => $body['name'],
                'description' => $body['description'] ?? '',
                'category' => $body['category'] ?? '',
                'quantity' => intval($body['quantity'] ?? 0),
                'unitPrice' => floatval($body['unitPrice'] ?? 0),
                'costPrice' => floatval($body['costPrice'] ?? 0),
                'reorderPoint' => intval($body['reorderPoint'] ?? 5),
                'createdAt' => date('c'),
                'updatedAt' => date('c')
            ];
            
            $inventory[] = $newProduct;
            $db->save('inventory', $inventory);
            
            successResponse($newProduct, 'Product added');
        }
        break;
        
    case 'DELETE':
        if (!$id) {
            errorResponse('Product ID required');
        }
        
        $inventory = $db->load('inventory');
        $filtered = array_filter($inventory, fn($p) => $p['id'] !== $id);
        
        if (count($filtered) === count($inventory)) {
            errorResponse('Product not found', 404);
        }
        
        $db->save('inventory', array_values($filtered));
        successResponse(null, 'Product deleted');
        break;
        
    default:
        errorResponse('Method not allowed', 405);
}
