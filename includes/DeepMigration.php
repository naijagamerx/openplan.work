<?php
/**
 * DeepMigration - Admin-only user portability tooling.
 *
 * Supports:
 * - Exporting a full user package (profile + encrypted collections + optional shared media)
 * - Previewing import packages with conflict analysis
 * - Executing replace/merge migration with progress tracking
 */

class DeepMigration
{
    private const MAX_UPLOAD_BYTES = 150 * 1024 * 1024; // 150MB

    private string $rootPath;
    private string $dataPath;
    private string $jobsRoot;
    private string $targetMasterPassword;
    private Database $globalDb;

    public function __construct(string $targetMasterPassword)
    {
        if ($targetMasterPassword === '') {
            throw new Exception('Master password is required');
        }

        $this->targetMasterPassword = $targetMasterPassword;
        $this->rootPath = ROOT_PATH;
        $this->dataPath = DATA_PATH;
        $this->jobsRoot = $this->dataPath . '/migrations/jobs';
        $this->globalDb = new Database($targetMasterPassword);

        if (!is_dir($this->jobsRoot) && !mkdir($this->jobsRoot, 0755, true)) {
            throw new Exception('Failed to initialize migration workspace');
        }
    }

    public function getUsers(): array
    {
        $users = Auth::allUsers();
        $result = [];
        foreach ($users as $user) {
            $result[] = [
                'id' => $user['id'] ?? '',
                'email' => $user['email'] ?? '',
                'name' => $user['name'] ?? '',
                'role' => Auth::normalizeRole($user['role'] ?? null)
            ];
        }
        return $result;
    }

    public function exportUserPackage(string $userId, bool $includeSharedMedia = true): string
    {
        $userId = trim($userId);
        if ($userId === '') {
            throw new Exception('User ID is required');
        }

        $users = $this->globalDb->load('users', true);
        $sourceUser = null;
        foreach ($users as $candidate) {
            if (($candidate['id'] ?? '') === $userId) {
                $sourceUser = $candidate;
                break;
            }
        }

        if (!is_array($sourceUser)) {
            throw new Exception('User not found');
        }

        $sourcePath = $this->dataPath . '/users/' . $userId;
        if (!is_dir($sourcePath)) {
            throw new Exception('Source user data directory not found');
        }

        $filename = 'deep_migration_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($sourceUser['email'] ?? $userId)) . '_' . date('Ymd_His') . '.zip';
        $tempPath = $this->jobsRoot . '/export_' . uniqid('', true) . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Failed to create export archive');
        }

        $collections = [];
        foreach ((glob($sourcePath . '/*.json.enc') ?: []) as $filePath) {
            $entry = basename($filePath);
            $collections[] = basename($entry, '.json.enc');
            $zip->addFile($filePath, 'data/' . $entry);
        }

        $exportUser = $sourceUser;
        $exportUser['authTokens'] = [];
        $zip->addFromString('user.json', json_encode($exportUser, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($includeSharedMedia) {
            $mediaDir = $this->rootPath . '/assets/media/pomodoro';
            $mediaFiles = glob($mediaDir . '/*.{mp3,wav,m4a,MP3,WAV,M4A}', GLOB_BRACE) ?: [];
            foreach ($mediaFiles as $mediaPath) {
                if (is_file($mediaPath)) {
                    $zip->addFile($mediaPath, 'assets/media/pomodoro/' . basename($mediaPath));
                }
            }

            $libraryPath = $mediaDir . '/library.json';
            if (is_file($libraryPath)) {
                $zip->addFile($libraryPath, 'assets/media/pomodoro/library.json');
            }
        }

        $manifest = [
            'type' => 'deep-migration-package',
            'version' => APP_VERSION,
            'created_at' => date('c'),
            'source_user_id' => $userId,
            'source_email' => $sourceUser['email'] ?? '',
            'collections' => $collections,
            'shared_media_included' => $includeSharedMedia
        ];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->close();

        if (!is_file($tempPath)) {
            throw new Exception('Failed to finalize export package');
        }

        // Return temp path; caller handles streaming and cleanup.
        return $tempPath . '::' . $filename;
    }

    public function previewUploadedPackage(string $uploadedFile, string $originalFilename, string $sourceMasterPassword, ?string $targetUserId = null): array
    {
        $this->validateUpload($uploadedFile, $originalFilename);
        $targetUserId = $this->normalizeOptional($targetUserId);

        $jobId = $this->newJobId();
        $jobDir = $this->jobDir($jobId);
        if (!mkdir($jobDir, 0755, true) && !is_dir($jobDir)) {
            throw new Exception('Failed to initialize migration job');
        }

        $packageZipPath = $jobDir . '/package.zip';
        if (!move_uploaded_file($uploadedFile, $packageZipPath) && !rename($uploadedFile, $packageZipPath)) {
            throw new Exception('Failed to store uploaded package');
        }

        $this->writeJobState($jobId, [
            'job_id' => $jobId,
            'status' => 'previewing',
            'stage' => 'validating package',
            'progress' => 5,
            'created_at' => date('c'),
            'package' => [
                'filename' => $originalFilename,
                'path' => $packageZipPath
            ]
        ]);

        $package = $this->analyzePackage($packageZipPath, $sourceMasterPassword, $targetUserId);
        $preview = $package['preview'];
        $preview['job_id'] = $jobId;
        if (isset($package['zip']) && $package['zip'] instanceof ZipArchive) {
            $package['zip']->close();
        }

        $this->writeJobState($jobId, [
            'job_id' => $jobId,
            'status' => 'preview_ready',
            'stage' => 'preview complete',
            'progress' => 100,
            'created_at' => date('c'),
            'preview' => $preview,
            'package' => [
                'filename' => $originalFilename,
                'path' => $packageZipPath
            ]
        ]);

        $this->audit(Audit::EVENT_IMPORT, 'deep_migration.preview', [
            'job_id' => $jobId,
            'source_email' => $preview['source_user']['email'] ?? null,
            'target_user_id' => $targetUserId,
            'collection_count' => count($preview['collections'] ?? [])
        ]);

        return $preview;
    }

    public function executeMigration(string $jobId, string $strategy, string $sourceMasterPassword, ?string $targetUserId = null, bool $includeSharedMedia = true): array
    {
        $strategy = strtolower(trim($strategy));
        if (!in_array($strategy, ['replace', 'merge'], true)) {
            throw new Exception('Invalid strategy. Expected replace or merge');
        }

        $targetUserId = $this->normalizeOptional($targetUserId);
        $state = $this->readJobState($jobId);
        if (!$state) {
            throw new Exception('Migration job not found');
        }

        $zipPath = (string)($state['package']['path'] ?? '');
        if ($zipPath === '' || !is_file($zipPath)) {
            throw new Exception('Migration package is no longer available');
        }

        $this->writeJobState($jobId, array_merge($state, [
            'status' => 'running',
            'stage' => 'loading package',
            'progress' => 8,
            'execution' => [
                'strategy' => $strategy,
                'target_user_id' => $targetUserId,
                'include_shared_media' => $includeSharedMedia,
                'started_at' => date('c')
            ]
        ]));

        $package = $this->analyzePackage($zipPath, $sourceMasterPassword, $targetUserId);
        $preview = $package['preview'];
        $collectionsData = $package['collections_data'];
        $mediaEntries = $package['media_entries'];
        $zip = $package['zip'];

        $this->updateProgress($jobId, 25, 'resolving target account');
        $resolvedTarget = $this->resolveTargetUser($preview['source_user'], $targetUserId, $strategy);
        $targetUser = $resolvedTarget['user'];
        $createdUser = $resolvedTarget['created'];
        $targetUserId = (string)($targetUser['id'] ?? '');

        $targetDb = new Database($this->targetMasterPassword, $targetUserId);
        $targetDb->save('key_check', [
            'id' => 'master-key-check',
            'version' => 1,
            'createdAt' => date('c')
        ]);

        $this->updateProgress($jobId, 45, 'migrating collections');

        $summary = [
            'job_id' => $jobId,
            'strategy' => $strategy,
            'target_user_id' => $targetUserId,
            'target_user_email' => $targetUser['email'] ?? '',
            'created_account' => $createdUser,
            'collections' => [],
            'created_count' => 0,
            'updated_count' => 0,
            'skipped_count' => 0,
            'conflicts_count' => 0,
            'media_written' => 0,
            'completed_at' => null
        ];

        $totalCollections = max(1, count($collectionsData));
        $index = 0;
        foreach ($collectionsData as $collectionName => $incomingData) {
            $index++;
            $progress = 45 + (int)floor(($index / $totalCollections) * 35);
            $this->updateProgress($jobId, min(80, $progress), 'processing collection: ' . $collectionName);

            $collectionStats = [
                'collection' => $collectionName,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'conflicts' => 0
            ];

            if ($strategy === 'replace') {
                $targetDb->save($collectionName, $incomingData);
                $count = $this->countRecords($incomingData);
                $collectionStats['updated'] = $count;
                $summary['updated_count'] += $count;
            } else {
                $existingData = $targetDb->load($collectionName, false);
                $merged = $this->mergeCollectionData($existingData, $incomingData, $collectionStats);
                $targetDb->save($collectionName, $merged);
                $summary['created_count'] += $collectionStats['created'];
                $summary['updated_count'] += $collectionStats['updated'];
                $summary['skipped_count'] += $collectionStats['skipped'];
                $summary['conflicts_count'] += $collectionStats['conflicts'];
            }

            $summary['collections'][] = $collectionStats;
        }

        if ($includeSharedMedia && !empty($mediaEntries)) {
            $this->updateProgress($jobId, 88, 'writing shared media');
            $summary['media_written'] = $this->writeSharedMedia($zip, $mediaEntries, $strategy);
        }

        $zip->close();

        $summary['completed_at'] = date('c');
        $this->writeJobState($jobId, array_merge($this->readJobState($jobId) ?? [], [
            'status' => 'completed',
            'stage' => 'completed',
            'progress' => 100,
            'summary' => $summary
        ]));

        $this->audit(Audit::EVENT_IMPORT, 'deep_migration.execute', [
            'job_id' => $jobId,
            'strategy' => $strategy,
            'target_user_id' => $targetUserId,
            'source_email' => $preview['source_user']['email'] ?? null,
            'created_count' => $summary['created_count'],
            'updated_count' => $summary['updated_count'],
            'skipped_count' => $summary['skipped_count'],
            'conflicts_count' => $summary['conflicts_count'],
            'media_written' => $summary['media_written']
        ]);

        return $summary;
    }

    public function getProgress(string $jobId): array
    {
        $state = $this->readJobState($jobId);
        if (!$state) {
            throw new Exception('Migration job not found');
        }
        return $state;
    }

    private function analyzePackage(string $zipPath, string $sourceMasterPassword, ?string $targetUserId = null): array
    {
        if ($sourceMasterPassword === '') {
            throw new Exception('Source master password is required');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new Exception('Failed to open migration package');
        }

        $manifest = $this->readJsonEntry($zip, 'manifest.json');
        $sourceUser = $this->resolveSourceUser($zip);
        $entries = $this->collectEntries($zip);

        $dataEntries = $entries['data'];
        if (empty($dataEntries)) {
            $zip->close();
            throw new Exception('Package does not contain encrypted workspace collections');
        }

        $sourceEncryption = new Encryption($sourceMasterPassword);
        $collectionsData = [];
        $collectionsPreview = [];
        $conflictsByCollection = [];
        $targetDb = null;

        if ($targetUserId !== null) {
            $targetDb = new Database($this->targetMasterPassword, $targetUserId);
        }

        foreach ($dataEntries as $entryName => $collectionName) {
            $contents = $zip->getFromName($entryName);
            if (!is_string($contents) || $contents === '') {
                $zip->close();
                throw new Exception('Failed to read encrypted collection: ' . $collectionName);
            }

            try {
                $decoded = $sourceEncryption->decrypt($contents);
            } catch (Exception $e) {
                $zip->close();
                throw new Exception('Master key mismatch or corrupted data in collection: ' . $collectionName);
            }

            $collectionsData[$collectionName] = $decoded;
            $recordCount = $this->countRecords($decoded);
            $conflictCount = 0;

            if ($targetDb instanceof Database) {
                $existing = $targetDb->load($collectionName, false);
                $conflictCount = $this->estimateConflicts($existing, $decoded);
            }

            $collectionsPreview[] = [
                'name' => $collectionName,
                'records' => $recordCount,
                'conflicts' => $conflictCount
            ];
            $conflictsByCollection[$collectionName] = $conflictCount;
        }

        $users = $this->globalDb->load('users', true);
        $targetUser = null;
        $emailConflict = null;
        if ($targetUserId !== null) {
            foreach ($users as $candidate) {
                if (($candidate['id'] ?? '') === $targetUserId) {
                    $targetUser = [
                        'id' => $candidate['id'] ?? '',
                        'email' => $candidate['email'] ?? '',
                        'name' => $candidate['name'] ?? '',
                        'role' => Auth::normalizeRole($candidate['role'] ?? null)
                    ];
                    break;
                }
            }
        }

        foreach ($users as $candidate) {
            $candidateEmail = strtolower((string)($candidate['email'] ?? ''));
            $sourceEmail = strtolower((string)($sourceUser['email'] ?? ''));
            if ($sourceEmail !== '' && $candidateEmail === $sourceEmail) {
                $candidateId = (string)($candidate['id'] ?? '');
                if ($targetUserId === null || $candidateId !== $targetUserId) {
                    $emailConflict = [
                        'id' => $candidateId,
                        'email' => $candidate['email'] ?? '',
                        'name' => $candidate['name'] ?? ''
                    ];
                    break;
                }
            }
        }

        $mediaEntries = $entries['media'];
        $preview = [
            'source_user' => $sourceUser,
            'target_user' => $targetUser,
            'manifest' => is_array($manifest) ? $manifest : null,
            'collections' => $collectionsPreview,
            'media_files' => count($mediaEntries),
            'conflicts' => [
                'target_user_exists' => $targetUser !== null,
                'email_conflict' => $emailConflict,
                'total_collection_conflicts' => array_sum($conflictsByCollection)
            ],
            'package' => [
                'filename' => basename($zipPath),
                'size' => filesize($zipPath),
                'collections' => array_keys($collectionsData)
            ]
        ];

        return [
            'preview' => $preview,
            'collections_data' => $collectionsData,
            'media_entries' => $mediaEntries,
            'zip' => $zip
        ];
    }

    private function collectEntries(ZipArchive $zip): array
    {
        $dataEntries = [];
        $mediaEntries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (!is_string($entry)) {
                continue;
            }

            $normalized = str_replace('\\', '/', trim($entry));
            if ($normalized === '' || str_ends_with($normalized, '/')) {
                continue;
            }
            if (str_contains($normalized, '../') || str_starts_with($normalized, '/')
                || preg_match('/^[A-Za-z]:\//', $normalized)) {
                throw new Exception('Invalid package entry path: ' . $normalized);
            }

            $leaf = basename($normalized);
            $isDataRoot = preg_match('/^[a-zA-Z0-9_-]+\.json\.enc$/', $leaf) === 1;
            $isDataNested = str_starts_with($normalized, 'data/') && $isDataRoot;

            if ($isDataRoot || $isDataNested) {
                $collectionName = basename($leaf, '.json.enc');
                $dataEntries[$normalized] = $collectionName;
                continue;
            }

            $isMedia = preg_match('/^assets\/media\/pomodoro\/[a-zA-Z0-9._-]+\.(mp3|wav|m4a)$/i', $normalized) === 1
                || $normalized === 'assets/media/pomodoro/library.json';
            if ($isMedia) {
                $mediaEntries[] = $normalized;
                continue;
            }

            if (in_array($normalized, ['manifest.json', 'user.json', 'users.json'], true)) {
                continue;
            }

            throw new Exception('Unsupported package entry: ' . $normalized);
        }

        return [
            'data' => $dataEntries,
            'media' => $mediaEntries
        ];
    }

    private function resolveSourceUser(ZipArchive $zip): array
    {
        $user = $this->readJsonEntry($zip, 'user.json');
        if (is_array($user)) {
            return $this->normalizeSourceUser($user);
        }

        $users = $this->readJsonEntry($zip, 'users.json');
        if (is_array($users) && isset($users[0]) && is_array($users[0])) {
            return $this->normalizeSourceUser($users[0]);
        }

        return [
            'id' => null,
            'email' => '',
            'name' => 'Imported User',
            'role' => Auth::ROLE_USER
        ];
    }

    private function normalizeSourceUser(array $user): array
    {
        return [
            'id' => $this->normalizeOptional($user['id'] ?? null),
            'email' => trim((string)($user['email'] ?? '')),
            'name' => trim((string)($user['name'] ?? 'Imported User')),
            'role' => Auth::normalizeRole($user['role'] ?? Auth::ROLE_USER),
            'passwordHash' => $user['passwordHash'] ?? null,
            'emailVerifiedAt' => $user['emailVerifiedAt'] ?? null
        ];
    }

    private function resolveTargetUser(array $sourceUser, ?string $targetUserId, string $strategy): array
    {
        $users = $this->globalDb->load('users', true);
        $target = null;

        if ($targetUserId !== null) {
            foreach ($users as $index => $candidate) {
                if (($candidate['id'] ?? '') === $targetUserId) {
                    $target = $candidate;
                    $target['_index'] = $index;
                    break;
                }
            }

            if (!$target) {
                throw new Exception('Selected target user does not exist');
            }

            if ($strategy === 'replace') {
                $target['name'] = $sourceUser['name'] ?: ($target['name'] ?? '');
                $target['role'] = Auth::normalizeRole($sourceUser['role'] ?? ($target['role'] ?? Auth::ROLE_USER));
                if (!empty($sourceUser['passwordHash'])) {
                    $target['passwordHash'] = $sourceUser['passwordHash'];
                }
                if (!empty($sourceUser['email'])) {
                    $target['email'] = $sourceUser['email'];
                }
                $target['authTokens'] = [];
                $target['updatedAt'] = date('c');
                $users[(int)$target['_index']] = $target;
                unset($users[(int)$target['_index']]['_index']);
                if (!$this->globalDb->save('users', $users)) {
                    throw new Exception('Failed to update target user profile');
                }
            }

            unset($target['_index']);
            return ['user' => $target, 'created' => false];
        }

        $newUser = [
            'id' => $sourceUser['id'] ?? null,
            'email' => trim((string)($sourceUser['email'] ?? '')),
            'name' => trim((string)($sourceUser['name'] ?? 'Imported User')),
            'role' => Auth::normalizeRole($sourceUser['role'] ?? Auth::ROLE_USER),
            'passwordHash' => $sourceUser['passwordHash'] ?? null,
            'createdAt' => date('c'),
            'updatedAt' => date('c'),
            'authTokens' => [],
            'emailVerifiedAt' => $sourceUser['emailVerifiedAt'] ?? null
        ];

        if (empty($newUser['id'])) {
            $newUser['id'] = $this->globalDb->generateId();
        }

        foreach ($users as $existing) {
            if (($existing['id'] ?? '') === $newUser['id']) {
                $newUser['id'] = $this->globalDb->generateId();
                break;
            }
        }

        if ($newUser['email'] === '') {
            throw new Exception('Source package is missing user email; select an existing target user instead');
        }

        foreach ($users as $existing) {
            if (strtolower((string)($existing['email'] ?? '')) === strtolower($newUser['email'])) {
                throw new Exception('A user with this email already exists. Choose a target user for replace/merge');
            }
        }

        if (empty($newUser['passwordHash'])) {
            $newUser['passwordHash'] = Encryption::hashPassword(bin2hex(random_bytes(12)));
        }

        $users[] = $newUser;
        if (!$this->globalDb->save('users', $users)) {
            throw new Exception('Failed to create target user');
        }

        return ['user' => $newUser, 'created' => true];
    }

    private function writeSharedMedia(ZipArchive $zip, array $mediaEntries, string $strategy): int
    {
        $written = 0;
        foreach ($mediaEntries as $entry) {
            $contents = $zip->getFromName($entry);
            if (!is_string($contents)) {
                continue;
            }

            $target = $this->rootPath . '/' . $entry;
            $targetDir = dirname($target);
            if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
                throw new Exception('Failed to prepare media directory');
            }

            if ($strategy === 'merge' && is_file($target)) {
                $existingHash = hash_file('sha256', $target);
                $incomingHash = hash('sha256', $contents);
                if ($existingHash === $incomingHash) {
                    continue;
                }
            }

            if (file_put_contents($target, $contents, LOCK_EX) === false) {
                throw new Exception('Failed to write shared media: ' . basename($entry));
            }
            $written++;
        }

        return $written;
    }

    private function estimateConflicts(mixed $existing, mixed $incoming): int
    {
        if (!$this->isSequentialArray($existing) || !$this->isSequentialArray($incoming)) {
            return 0;
        }

        $existingIds = [];
        foreach ($existing as $item) {
            if (is_array($item) && isset($item['id'])) {
                $existingIds[(string)$item['id']] = true;
            }
        }

        if (empty($existingIds)) {
            return 0;
        }

        $count = 0;
        foreach ($incoming as $item) {
            if (is_array($item) && isset($item['id']) && isset($existingIds[(string)$item['id']])) {
                $count++;
            }
        }
        return $count;
    }

    private function mergeCollectionData(mixed $existing, mixed $incoming, array &$stats): mixed
    {
        if ($this->isSequentialArray($existing) && $this->isSequentialArray($incoming)) {
            return $this->mergeSequentialCollection($existing, $incoming, $stats);
        }

        if (is_array($existing) && is_array($incoming)) {
            foreach ($incoming as $key => $value) {
                if (!array_key_exists($key, $existing)) {
                    $existing[$key] = $value;
                    $stats['created']++;
                    continue;
                }

                if ($existing[$key] === $value) {
                    $stats['skipped']++;
                    continue;
                }

                $existing[$key] = $value;
                $stats['updated']++;
            }
            return $existing;
        }

        if ($existing === $incoming) {
            $stats['skipped']++;
            return $existing;
        }

        $stats['updated']++;
        return $incoming;
    }

    private function mergeSequentialCollection(array $existing, array $incoming, array &$stats): array
    {
        $existingById = [];
        $hasIdModel = false;
        foreach ($existing as $idx => $item) {
            if (is_array($item) && isset($item['id'])) {
                $hasIdModel = true;
                $existingById[(string)$item['id']] = $idx;
            }
        }

        if (!$hasIdModel) {
            foreach ($incoming as $item) {
                $incomingHash = hash('sha256', json_encode($item));
                $found = false;
                foreach ($existing as $current) {
                    if ($incomingHash === hash('sha256', json_encode($current))) {
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    $stats['skipped']++;
                } else {
                    $existing[] = $item;
                    $stats['created']++;
                }
            }
            return $existing;
        }

        foreach ($incoming as $item) {
            if (!is_array($item) || !isset($item['id'])) {
                $existing[] = $item;
                $stats['created']++;
                continue;
            }

            $id = (string)$item['id'];
            if (!isset($existingById[$id])) {
                $existing[] = $item;
                $existingById[$id] = count($existing) - 1;
                $stats['created']++;
                continue;
            }

            $existingIndex = $existingById[$id];
            if ($existing[$existingIndex] === $item) {
                $stats['skipped']++;
                continue;
            }

            $existing[$existingIndex] = array_merge($existing[$existingIndex], $item);
            $stats['updated']++;
            $stats['conflicts']++;
        }

        return $existing;
    }

    private function countRecords(mixed $data): int
    {
        if (!is_array($data)) {
            return 0;
        }
        return count($data);
    }

    private function isSequentialArray(mixed $data): bool
    {
        if (!is_array($data)) {
            return false;
        }
        return array_keys($data) === range(0, count($data) - 1);
    }

    private function readJsonEntry(ZipArchive $zip, string $entry): ?array
    {
        $contents = $zip->getFromName($entry);
        if (!is_string($contents) || $contents === '') {
            return null;
        }
        $decoded = json_decode($contents, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function updateProgress(string $jobId, int $progress, string $stage): void
    {
        $state = $this->readJobState($jobId) ?? ['job_id' => $jobId];
        $state['progress'] = max(0, min(100, $progress));
        $state['stage'] = $stage;
        $state['status'] = 'running';
        $this->writeJobState($jobId, $state);
    }

    private function validateUpload(string $uploadedFile, string $originalFilename): void
    {
        if (!is_file($uploadedFile)) {
            throw new Exception('Uploaded file is missing');
        }

        if (strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION)) !== 'zip') {
            throw new Exception('Only ZIP packages are supported');
        }

        $size = filesize($uploadedFile);
        if ($size === false || $size <= 0) {
            throw new Exception('Uploaded package is empty');
        }

        if ($size > self::MAX_UPLOAD_BYTES) {
            throw new Exception('Migration package is too large (max 150MB)');
        }
    }

    private function normalizeOptional(mixed $value): ?string
    {
        $val = trim((string)$value);
        return $val === '' ? null : $val;
    }

    private function newJobId(): string
    {
        return 'mig_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    }

    private function jobDir(string $jobId): string
    {
        return $this->jobsRoot . '/' . basename($jobId);
    }

    private function statePath(string $jobId): string
    {
        return $this->jobDir($jobId) . '/state.json';
    }

    private function writeJobState(string $jobId, array $state): void
    {
        $jobDir = $this->jobDir($jobId);
        if (!is_dir($jobDir) && !mkdir($jobDir, 0755, true) && !is_dir($jobDir)) {
            throw new Exception('Failed to prepare job state path');
        }

        $state['updated_at'] = date('c');
        file_put_contents($this->statePath($jobId), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private function readJobState(string $jobId): ?array
    {
        $statePath = $this->statePath($jobId);
        if (!is_file($statePath)) {
            return null;
        }
        $decoded = json_decode((string)file_get_contents($statePath), true);
        return is_array($decoded) ? $decoded : null;
    }

    private function audit(string $event, string $resourceId, array $details): void
    {
        try {
            $audit = new Audit(new Database($this->targetMasterPassword, Auth::userId()));
            $audit->log($event, [
                'resource_type' => 'migration',
                'resource_id' => $resourceId,
                'details' => $details
            ]);
        } catch (Exception $e) {
            // Do not block migration flow on audit write failures.
        }
    }
}
