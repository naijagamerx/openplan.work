<?php
require_once __DIR__ . '/BaseAPI.php';
require_once __DIR__ . '/Auth.php';

class MeetingsAPI extends BaseAPI {
    protected function getAllowedFields(): array {
        return [
            'title',
            'description',
            'date',
            'startTime',
            'endTime',
            'location',
            'attendees',
            'color',
            'projectId',
            'clientId',
            'userId',
            'createdAt',
            'updatedAt'
        ];
    }

    public function create(array $data): ?array {
        $data['userId'] = Auth::userId();
        $data['createdAt'] = date('c');
        $data['updatedAt'] = date('c');
        $data['attendees'] = $this->normalizeAttendees($data['attendees'] ?? []);
        $data['color'] = $data['color'] ?? '#a855f7';
        return parent::create($data);
    }

    public function update(string $id, array $data): ?array {
        unset($data['userId']);
        $data['updatedAt'] = date('c');
        if (isset($data['attendees'])) {
            $data['attendees'] = $this->normalizeAttendees($data['attendees']);
        }
        return parent::update($id, $data);
    }

    public function findAll(array $filters = []): array {
        $meetings = parent::findAll();

        usort($meetings, function ($a, $b) {
            $aKey = ($a['date'] ?? '') . ' ' . ($a['startTime'] ?? '');
            $bKey = ($b['date'] ?? '') . ' ' . ($b['startTime'] ?? '');
            return strcmp($aKey, $bKey);
        });

        if (!empty($filters['month']) && !empty($filters['year'])) {
            $month = str_pad((string)$filters['month'], 2, '0', STR_PAD_LEFT);
            $year = (string)$filters['year'];
            $prefix = $year . '-' . $month;
            $meetings = array_values(array_filter($meetings, function ($m) use ($prefix) {
                return strpos((string)($m['date'] ?? ''), $prefix) === 0;
            }));
        }

        if (!empty($filters['date'])) {
            $date = (string)$filters['date'];
            $meetings = array_values(array_filter($meetings, function ($m) use ($date) {
                return ($m['date'] ?? '') === $date;
            }));
        }

        if (!empty($filters['projectId'])) {
            $projectId = (string)$filters['projectId'];
            $meetings = array_values(array_filter($meetings, function ($m) use ($projectId) {
                return ($m['projectId'] ?? '') === $projectId;
            }));
        }

        if (!empty($filters['clientId'])) {
            $clientId = (string)$filters['clientId'];
            $meetings = array_values(array_filter($meetings, function ($m) use ($clientId) {
                return ($m['clientId'] ?? '') === $clientId;
            }));
        }

        return $meetings;
    }

    private function normalizeAttendees($attendees): array {
        if (is_string($attendees)) {
            $attendees = array_map('trim', explode(',', $attendees));
        }
        if (!is_array($attendees)) {
            return [];
        }
        return array_values(array_filter(array_map(function ($a) {
            return is_string($a) ? trim($a) : $a;
        }, $attendees), function ($a) {
            return $a !== '' && $a !== null;
        }));
    }
}
