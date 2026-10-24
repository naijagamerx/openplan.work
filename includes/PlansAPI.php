<?php
require_once __DIR__ . '/BaseAPI.php';

class PlansAPI extends BaseAPI {
    protected function getAllowedFields(): array {
        return [
            'name',
            'price',
            'currency',
            'durationDays',
            'providers',
            'monthlyRequestCap',
            'description',
            'active',
            'sortOrder',
            'createdAt',
            'updatedAt'
        ];
    }

    public function create(array $data): ?array {
        $data['createdAt'] = date('c');
        $data['updatedAt'] = date('c');
        $data['active'] = isset($data['active']) ? (bool)$data['active'] : true;
        $data['providers'] = $this->normalizeProviders($data['providers'] ?? []);
        if (empty($data['providers'])) {
            throw new Exception('A plan must include at least one AI provider');
        }
        $data['monthlyRequestCap'] = max(0, (int)($data['monthlyRequestCap'] ?? 0));
        $data['durationDays'] = max(1, (int)($data['durationDays'] ?? 30));
        $data['price'] = round((float)($data['price'] ?? 0), 2);
        $data['currency'] = strtoupper(trim((string)($data['currency'] ?? 'USD'))) ?: 'USD';
        $data['sortOrder'] = (int)($data['sortOrder'] ?? 0);
        return parent::create($data);
    }

    public function update(string $id, array $data): ?array {
        $data['updatedAt'] = date('c');
        if (isset($data['providers'])) {
            $data['providers'] = $this->normalizeProviders($data['providers']);
            if (empty($data['providers'])) {
                throw new Exception('A plan must include at least one AI provider');
            }
        }
        if (isset($data['monthlyRequestCap'])) {
            $data['monthlyRequestCap'] = max(0, (int)$data['monthlyRequestCap']);
        }
        if (isset($data['durationDays'])) {
            $data['durationDays'] = max(1, (int)$data['durationDays']);
        }
        if (isset($data['price'])) {
            $data['price'] = round((float)$data['price'], 2);
        }
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper(trim((string)$data['currency'])) ?: 'USD';
        }
        if (isset($data['active'])) {
            $data['active'] = (bool)$data['active'];
        }
        return parent::update($id, $data);
    }

    public function findAll(array $filters = []): array {
        $plans = parent::findAll();
        if (!empty($filters['activeOnly'])) {
            $plans = array_values(array_filter($plans, fn($p) => !empty($p['active'])));
        }
        usort($plans, function ($a, $b) {
            $aOrder = (int)($a['sortOrder'] ?? 0);
            $bOrder = (int)($b['sortOrder'] ?? 0);
            if ($aOrder !== $bOrder) {
                return $aOrder - $bOrder;
            }
            return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });
        return $plans;
    }

    private function normalizeProviders($value): array {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }
        if (!is_array($value)) {
            return [];
        }
        $allowed = ['groq', 'openrouter', 'gemini', 'ollama'];
        $clean = [];
        foreach ($value as $v) {
            $v = strtolower(trim((string)$v));
            if ($v !== '' && in_array($v, $allowed, true) && !in_array($v, $clean, true)) {
                $clean[] = $v;
            }
        }
        return $clean;
    }
}
