<?php
require_once __DIR__ . '/BaseAPI.php';

class PaymentMethodsAPI extends BaseAPI {
    protected function getAllowedFields(): array {
        return [
            'type',
            'label',
            'active',
            'instructions',
            // Crypto fields
            'network',
            'walletAddress',
            'qrCodeUrl',
            // Bank fields
            'bankName',
            'accountName',
            'accountNumber',
            'branchCode',
            'swift',
            // Meta
            'sortOrder',
            'createdAt',
            'updatedAt'
        ];
    }

    public function create(array $data): ?array {
        $data['createdAt'] = date('c');
        $data['updatedAt'] = date('c');
        $data['type'] = $this->normalizeType($data['type'] ?? '');
        $data['active'] = isset($data['active']) ? (bool)$data['active'] : true;
        $data['sortOrder'] = (int)($data['sortOrder'] ?? 0);
        return parent::create($data);
    }

    public function update(string $id, array $data): ?array {
        $data['updatedAt'] = date('c');
        if (isset($data['type'])) {
            $data['type'] = $this->normalizeType($data['type']);
        }
        if (isset($data['active'])) {
            $data['active'] = (bool)$data['active'];
        }
        return parent::update($id, $data);
    }

    public function findAll(array $filters = []): array {
        $methods = parent::findAll();
        if (!empty($filters['activeOnly'])) {
            $methods = array_values(array_filter($methods, fn($m) => !empty($m['active'])));
        }
        if (!empty($filters['type'])) {
            $type = $this->normalizeType($filters['type']);
            $methods = array_values(array_filter($methods, fn($m) => ($m['type'] ?? '') === $type));
        }
        usort($methods, function ($a, $b) {
            $aOrder = (int)($a['sortOrder'] ?? 0);
            $bOrder = (int)($b['sortOrder'] ?? 0);
            if ($aOrder !== $bOrder) {
                return $aOrder - $bOrder;
            }
            return strcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        });
        return $methods;
    }

    private function normalizeType(string $type): string {
        $type = strtolower(trim($type));
        return in_array($type, ['crypto', 'bank'], true) ? $type : 'bank';
    }
}
