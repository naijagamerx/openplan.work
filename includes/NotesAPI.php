<?php
require_once __DIR__ . '/BaseAPI.php';
require_once __DIR__ . '/Auth.php';

class NotesAPI extends BaseAPI {
    protected function getAllowedFields(): array {
        return [
            'title',
            'content',
            'tags',
            'color',
            'isPinned',
            'isFavorite',
            'linkedEntityType',
            'linkedEntityId',
            'userId',
            'createdAt',
            'updatedAt'
        ];
    }

    public function create(array $data): ?array {
        $data['userId'] = Auth::userId();
        $data['createdAt'] = date('c');
        $data['updatedAt'] = date('c');
        $data['isPinned'] = $data['isPinned'] ?? false;
        $data['isFavorite'] = $data['isFavorite'] ?? false;
        $data['tags'] = $this->processTags($data['tags'] ?? []);
        return parent::create($data);
    }

    public function update(string $id, array $data): ?array {
        unset($data['userId']);
        $data['updatedAt'] = date('c');

        // Process tags if provided
        if (isset($data['tags'])) {
            $data['tags'] = $this->processTags($data['tags']);
        }

        return parent::update($id, $data);
    }

    /**
     * Process tags - ensure they are unique and normalized
     */
    private function processTags(array $tags): array {
        if (is_string($tags)) {
            // Convert comma-separated string to array
            $tags = array_map('trim', explode(',', $tags));
        }

        // Normalize and remove duplicates
        $normalized = array_map(function($tag) {
            return trim(strtolower($tag));
        }, $tags);

        return array_values(array_filter(array_unique($normalized)));
    }

    /**
     * Find all notes with optional filters
     */
    public function findAll(array $filters = []): array {
        $notes = parent::findAll();

        // Sort by pinned first, then by updated date descending
        usort($notes, function($a, $b) {
            if (($a['isPinned'] ?? false) !== ($b['isPinned'] ?? false)) {
                return ($a['isPinned'] ?? false) ? -1 : 1;
            }
            return strtotime($b['updatedAt'] ?? $b['createdAt'] ?? '')
                 - strtotime($a['updatedAt'] ?? $a['createdAt'] ?? '');
        });

        // Apply filters
        if (!empty($filters['tag'])) {
            $tag = strtolower(trim($filters['tag']));
            $notes = array_filter($notes, function($note) use ($tag) {
                return in_array($tag, array_map('strtolower', $note['tags'] ?? []));
            });
            $notes = array_values($notes);
        }

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $notes = array_filter($notes, function($note) use ($search) {
                return strpos(strtolower($note['title'] ?? ''), $search) !== false
                    || strpos(strtolower($note['content'] ?? ''), $search) !== false;
            });
            $notes = array_values($notes);
        }

        if (!empty($filters['isPinned'])) {
            $notes = array_filter($notes, function($note) {
                return $note['isPinned'] ?? false;
            });
            $notes = array_values($notes);
        }

        if (!empty($filters['isFavorite'])) {
            $notes = array_filter($notes, function($note) {
                return $note['isFavorite'] ?? false;
            });
            $notes = array_values($notes);
        }

        if (!empty($filters['linkedEntityType']) && !empty($filters['linkedEntityId'])) {
            $notes = array_filter($notes, function($note) use ($filters) {
                return ($note['linkedEntityType'] ?? '') === $filters['linkedEntityType']
                    && ($note['linkedEntityId'] ?? '') === $filters['linkedEntityId'];
            });
            $notes = array_values($notes);
        }

        return $notes;
    }

    /**
     * Get all unique tags from all notes
     */
    public function getAllTags(): array {
        $notes = parent::findAll();
        $allTags = [];

        foreach ($notes as $note) {
            foreach ($note['tags'] ?? [] as $tag) {
                $tag = strtolower(trim($tag));
                if (!empty($tag) && !in_array($tag, $allTags)) {
                    $allTags[] = $tag;
                }
            }
        }

        sort($allTags);
        return $allTags;
    }

    /**
     * Search notes by query (title or content)
     */
    public function search(string $query): array {
        return $this->findAll(['search' => $query]);
    }

    /**
     * Toggle pin status
     */
    public function togglePin(string $id): ?array {
        $note = $this->find($id);
        if (!$note) {
            return null;
        }

        $currentPin = $note['isPinned'] ?? false;
        return $this->update($id, ['isPinned' => !$currentPin]);
    }

    /**
     * Toggle favorite status
     */
    public function toggleFavorite(string $id): ?array {
        $note = $this->find($id);
        if (!$note) {
            return null;
        }

        $currentFav = $note['isFavorite'] ?? false;
        return $this->update($id, ['isFavorite' => !$currentFav]);
    }

    /**
     * Add a tag to a note
     */
    public function addTag(string $id, string $tag): ?array {
        $note = $this->find($id);
        if (!$note) {
            return null;
        }

        $tags = $note['tags'] ?? [];
        $normalizedTag = strtolower(trim($tag));

        if (!in_array($normalizedTag, $tags)) {
            $tags[] = $normalizedTag;
            return $this->update($id, ['tags' => $tags]);
        }

        return $note;
    }

    /**
     * Remove a tag from a note
     */
    public function removeTag(string $id, string $tag): ?array {
        $note = $this->find($id);
        if (!$note) {
            return null;
        }

        $tags = $note['tags'] ?? [];
        $normalizedTag = strtolower(trim($tag));

        $filtered = array_values(array_filter($tags, function($t) use ($normalizedTag) {
            return $t !== $normalizedTag;
        }));

        return $this->update($id, ['tags' => $filtered]);
    }

    /**
     * Delete a tag globally from all notes
     * @param string $tag - The tag to delete
     * @return int - Number of notes affected
     */
    public function deleteTagGlobal(string $tag): int {
        $normalizedTag = strtolower(trim($tag));
        if ($normalizedTag === '') {
            return 0;
        }

        $notes = parent::findAll();
        $affectedCount = 0;

        foreach ($notes as $note) {
            $tags = $note['tags'] ?? [];
            if (!is_array($tags)) {
                continue;
            }

            $noteHadTag = false;
            $filtered = [];

            foreach ($tags as $existingTag) {
                if (!is_scalar($existingTag)) {
                    continue;
                }

                $normalizedExistingTag = strtolower(trim((string) $existingTag));
                if ($normalizedExistingTag === '') {
                    continue;
                }

                if ($normalizedExistingTag === $normalizedTag) {
                    $noteHadTag = true;
                    continue;
                }

                $filtered[] = $normalizedExistingTag;
            }

            $filtered = array_values(array_unique($filtered));

            // Only update if tag was actually removed
            if ($noteHadTag) {
                $this->update($note['id'], ['tags' => $filtered]);
                $affectedCount++;
            }
        }

        return $affectedCount;
    }

    /**
     * Get tag usage statistics
     * @return array - Array of ['tag' => string, 'count' => int]
     */
    public function getTagStats(): array {
        $notes = parent::findAll();
        $tagCounts = [];

        foreach ($notes as $note) {
            $tags = $note['tags'] ?? [];
            if (!is_array($tags)) {
                continue;
            }

            foreach ($tags as $rawTag) {
                if (!is_scalar($rawTag)) {
                    continue;
                }

                $normalizedTag = strtolower(trim((string) $rawTag));
                if ($normalizedTag === '') {
                    continue;
                }

                if (!isset($tagCounts[$normalizedTag])) {
                    $tagCounts[$normalizedTag] = 0;
                }
                $tagCounts[$normalizedTag]++;
            }
        }

        $stats = [];
        foreach ($tagCounts as $tag => $count) {
            $stats[] = ['tag' => $tag, 'count' => $count];
        }

        // Sort by tag name
        usort($stats, fn($a, $b) => strcmp($a['tag'], $b['tag']));

        return $stats;
    }

    /**
     * Bulk import notes
     * @param array $notes - Array of note data
     * @return array - ['created' => int, 'errors' => array]
     */
    public function bulkImport(array $notes): array {
        $result = ['created' => 0, 'errors' => []];

        foreach ($notes as $index => $noteData) {
            try {
                // Validate required fields
                if (empty($noteData['title'])) {
                    $result['errors'][] = "Row {$index}: Missing title";
                    continue;
                }

                // Prepare note data
                $data = [
                    'title' => trim($noteData['title']),
                    'content' => $noteData['content'] ?? '',
                    'tags' => $this->processTags($noteData['tags'] ?? []),
                    'isPinned' => filter_var($noteData['isPinned'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'isFavorite' => filter_var($noteData['isFavorite'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'color' => $noteData['color'] ?? null
                ];

                // Create the note
                $created = $this->create($data);
                if ($created) {
                    $result['created']++;
                } else {
                    $result['errors'][] = "Row {$index}: Failed to create note";
                }
            } catch (Exception $e) {
                $result['errors'][] = "Row {$index}: {$e->getMessage()}";
            }
        }

        return $result;
    }

    /**
     * Get note count
     */
    public function count(): int {
        return count(parent::findAll());
    }

    /**
     * Get notes by entity (task or project)
     */
    public function findByEntity(string $entityType, string $entityId): array {
        return $this->findAll([
            'linkedEntityType' => $entityType,
            'linkedEntityId' => $entityId
        ]);
    }

    /**
     * Bulk delete multiple notes
     * @param array $ids - Array of note IDs to delete
     * @return array - ['deleted' => int, 'failed' => array]
     */
    public function bulkDelete(array $ids): array {
        $result = ['deleted' => 0, 'failed' => []];
        $currentUserId = Auth::userId();

        foreach ($ids as $id) {
            try {
                // Verify ownership before deleting
                $note = $this->find($id);
                if (!$note) {
                    $result['failed'][] = ['id' => $id, 'reason' => 'Not found'];
                    continue;
                }

                // Check user owns this note
                if ($note['userId'] !== $currentUserId) {
                    $result['failed'][] = ['id' => $id, 'reason' => 'Unauthorized'];
                    continue;
                }

                // Delete the note
                if ($this->delete($id)) {
                    $result['deleted']++;
                } else {
                    $result['failed'][] = ['id' => $id, 'reason' => 'Delete failed'];
                }
            } catch (Exception $e) {
                $result['failed'][] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        return $result;
    }
}
