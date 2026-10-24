<?php
/**
 * AISharedKeysAPI — admin-only read/write of admin_shared_keys.
 *
 * Storage: global encrypted file data/admin_shared_keys.json.enc
 *          (Database(APP_NAME), single-record dict).
 *
 * Record schema:
 *   {
 *     groqApiKey, openrouterApiKey, geminiApiKey,
 *     models: { groq: [...], openrouter: [...], gemini: [...] },
 *     updatedAt, updatedBy
 *   }
 *
 * SECURITY: getMaskedKeys() NEVER returns raw key material — only
 * { configured: bool, lastFour: "xyz1" } so the admin UI can confirm
 * which providers are set without re-exposing the full key.
 */

require_once __DIR__ . '/Database.php';

class AISharedKeysAPI {
    private const COLLECTION = 'admin_shared_keys';
    private const KEY_FIELDS = ['groqApiKey', 'openrouterApiKey', 'geminiApiKey'];
    private const PROVIDERS = ['groq', 'openrouter', 'gemini'];

    private Database $db;

    public function __construct() {
        $this->db = new Database(APP_NAME);
    }

    /**
     * Returns a payload safe to send to the admin client.
     * Never includes raw key material; includes model counts per provider.
     */
    public function getMaskedKeys(): array {
        $record = $this->loadRecord();
        $masked = [];
        foreach (self::KEY_FIELDS as $field) {
            $key = (string)($record[$field] ?? '');
            $masked[$field] = [
                'configured' => $key !== '',
                'lastFour' => $key !== '' ? substr($key, -4) : null
            ];
        }
        $models = is_array($record['models'] ?? null) ? $record['models'] : [];
        $modelSummary = [];
        foreach (self::PROVIDERS as $provider) {
            $providerModels = is_array($models[$provider] ?? null) ? $models[$provider] : [];
            $enabledCount = 0;
            $defaultModelId = null;
            foreach ($providerModels as $m) {
                if (!empty($m['enabled'])) {
                    $enabledCount++;
                }
                if (!empty($m['isDefault'])) {
                    $defaultModelId = $m['modelId'] ?? null;
                }
            }
            $modelSummary[$provider] = [
                'total' => count($providerModels),
                'enabled' => $enabledCount,
                'default' => $defaultModelId
            ];
        }
        $masked['models'] = $modelSummary;
        $masked['updatedAt'] = $record['updatedAt'] ?? null;
        $masked['updatedBy'] = $record['updatedBy'] ?? null;
        return $masked;
    }

    /**
     * Save admin keys. Partial updates allowed.
     * Models is replaced wholesale when provided.
     */
    public function saveKeys(array $input, string $adminUserId): bool {
        $record = $this->loadRecord();
        foreach (self::KEY_FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $record[$field] = trim((string)$input[$field]);
            }
        }
        if (array_key_exists('models', $input) && is_array($input['models'])) {
            $record['models'] = $this->sanitizeModels($input['models']);
        }
        $record['updatedAt'] = date('c');
        $record['updatedBy'] = $adminUserId;
        return (bool)$this->db->save(self::COLLECTION, $record);
    }

    /**
     * Import API keys + models from the admin's own per-user settings.
     * Reads admin's config + models collections, copies to the global record.
     * Returns the count of providers / models imported.
     */
    public function importFromAdminSettings(string $adminUserId): array {
        $masterPassword = getMasterPassword();
        if ($masterPassword === '' || $adminUserId === '') {
            throw new Exception('Admin session not available');
        }
        $adminDb = new Database($masterPassword, $adminUserId);

        $config = $adminDb->load('config', false);
        $models = $adminDb->load('models');
        if (!is_array($config)) {
            $config = [];
        }
        if (!is_array($models)) {
            $models = [];
        }

        $record = $this->loadRecord();
        $providersImported = [];
        $modelsImported = 0;
        foreach (self::KEY_FIELDS as $field) {
            $value = trim((string)($config[$field] ?? ''));
            if ($value !== '') {
                $record[$field] = $value;
                $providersImported[] = $field;
            }
        }

        $cleanModels = is_array($record['models'] ?? null) ? $record['models'] : [];
        foreach (self::PROVIDERS as $provider) {
            if (!isset($models[$provider]) || !is_array($models[$provider])) {
                continue;
            }
            $cleanModels[$provider] = $this->sanitizeProviderModels($models[$provider]);
            $modelsImported += count($cleanModels[$provider]);
        }
        $record['models'] = $cleanModels;
        $record['updatedAt'] = date('c');
        $record['updatedBy'] = $adminUserId;
        $record['lastImportedAt'] = date('c');

        if (!$this->db->save(self::COLLECTION, $record)) {
            throw new Exception('Failed to persist imported shared keys');
        }
        return [
            'providersImported' => $providersImported,
            'modelsImported' => $modelsImported
        ];
    }

    /**
     * Used by server-side AI flow to fetch the actual key for a provider.
     * NEVER expose this through an HTTP endpoint.
     */
    public function getRawKey(string $provider): ?string {
        $map = [
            'groq' => 'groqApiKey',
            'openrouter' => 'openrouterApiKey',
            'gemini' => 'geminiApiKey'
        ];
        $field = $map[$provider] ?? null;
        if ($field === null) {
            return null;
        }
        $record = $this->loadRecord();
        $key = (string)($record[$field] ?? '');
        return $key !== '' ? $key : null;
    }

    /**
     * Full enabled model records for a provider, drawn from the shared record.
     * Returns array of ['modelId', 'displayName', 'isDefault', 'enabled'] in storage order.
     * Used by the user-facing AI assistant when the user is consuming shared keys.
     */
    public function getSharedModels(string $provider): array {
        $record = $this->loadRecord();
        $models = $record['models'][$provider] ?? [];
        if (!is_array($models)) {
            return [];
        }
        $out = [];
        foreach ($models as $m) {
            if (empty($m['enabled']) || empty($m['modelId'])) {
                continue;
            }
            $out[] = [
                'modelId'     => (string)$m['modelId'],
                'displayName' => (string)($m['displayName'] ?? $m['modelId']),
                'isDefault'   => !empty($m['isDefault']),
                'enabled'     => true
            ];
        }
        return $out;
    }

    /**
     * Enabled model IDs for a provider, drawn from the shared record.
     * Returns array of modelId strings.
     */
    public function getSharedModelIds(string $provider): array {
        $record = $this->loadRecord();
        $models = $record['models'][$provider] ?? [];
        if (!is_array($models)) {
            return [];
        }
        $ids = [];
        foreach ($models as $m) {
            if (!empty($m['enabled']) && !empty($m['modelId'])) {
                $ids[] = (string)$m['modelId'];
            }
        }
        return $ids;
    }

    private function loadRecord(): array {
        try {
            $record = $this->db->load(self::COLLECTION, false);
        } catch (Exception $e) {
            error_log('AISharedKeysAPI load failed: ' . $e->getMessage());
            return [];
        }
        return is_array($record) ? $record : [];
    }

    private function sanitizeModels(array $models): array {
        $clean = [];
        foreach (self::PROVIDERS as $provider) {
            if (isset($models[$provider]) && is_array($models[$provider])) {
                $clean[$provider] = $this->sanitizeProviderModels($models[$provider]);
            }
        }
        return $clean;
    }

    private function sanitizeProviderModels(array $providerModels): array {
        $clean = [];
        foreach ($providerModels as $m) {
            if (!is_array($m)) {
                continue;
            }
            $modelId = trim((string)($m['modelId'] ?? ''));
            if ($modelId === '') {
                continue;
            }
            $clean[] = [
                'id' => $m['id'] ?? bin2hex(random_bytes(8)),
                'modelId' => $modelId,
                'displayName' => trim((string)($m['displayName'] ?? $modelId)),
                'description' => trim((string)($m['description'] ?? '')),
                'enabled' => !empty($m['enabled']),
                'isDefault' => !empty($m['isDefault'])
            ];
        }
        return $clean;
    }
}
