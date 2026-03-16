<?php
// Pomodoro Timer API
require_once __DIR__ . '/../config.php';

const POMODORO_SHARED_MUSIC_DIR = ROOT_PATH . '/assets/media/pomodoro';
const POMODORO_LEGACY_MUSIC_DIR = DATA_PATH . '/uploads/pomodoro';
const POMODORO_SHARED_MUSIC_METADATA = ROOT_PATH . '/assets/media/pomodoro/library.json';

try {
    if (!Auth::check()) {
        errorResponse('Unauthorized', 401, ERROR_UNAUTHORIZED);
    }

    $isWriteRequest = requestMethod() !== 'GET';
    if ($isWriteRequest && !Auth::isMcp()) {
        $body = getJsonBody();
        $csrfToken = $body['csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403, ERROR_FORBIDDEN);
        }
    }

    $db = new Database(getMasterPassword(), Auth::userId());
    $action = $_GET['action'] ?? '';
    $input = getJsonBody();

    if ($action === 'music_download') {
        handleMusicDownload($db);
        exit;
    }

    header('Content-Type: application/json');

    switch ($action) {
        case 'complete':
            $mode = $input['mode'] ?? 25;
            $duration = $input['duration'] ?? 1500;

            $sessions = $db->load('pomodoro_sessions', true) ?? [];
            $sessions[] = [
                'id' => uniqid(),
                'mode' => $mode . ' minutes',
                'duration' => $duration,
                'date' => date('Y-m-d H:i:s'),
                'status' => 'completed'
            ];
            $db->save('pomodoro_sessions', $sessions);

            echo json_encode(['success' => true, 'data' => ['id' => $sessions[count($sessions) - 1]['id']]]);
            break;

        case 'list':
            $sessions = $db->load('pomodoro_sessions', true) ?? [];
            echo json_encode(['success' => true, 'data' => array_reverse($sessions)]);
            break;

        case 'music_list':
            handleMusicList($db);
            break;

        case 'music_upload':
            handleMusicUpload($db);
            break;

        case 'music_delete':
            handleMusicDelete($db, $input);
            break;

        case 'music_rename':
            handleMusicRename($db, $input);
            break;

        case 'shared_music_list':
            Auth::requireAdmin();
            handleSharedMusicList();
            break;

        case 'shared_music_upload':
            Auth::requireAdmin();
            handleSharedMusicUpload();
            break;

        case 'shared_music_delete':
            Auth::requireAdmin();
            handleSharedMusicDelete($input);
            break;

        case 'shared_music_rename':
            Auth::requireAdmin();
            handleSharedMusicRename($input);
            break;

        default:
            echo json_encode(['success' => false, 'error' => ['code' => 'UNKNOWN_ACTION', 'message' => 'Invalid action']]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => $e->getMessage()]]);
}

function handleMusicList(Database $db): void {
    migrateLegacyMusicToSharedDir($db);
    $music = pruneMissingTracks($db, $db->load('pomodoro_music', true) ?? []);
    $music = mergeBundledSharedTracks($music);
    $music = array_map(static function(array $track): array {
        return decorateTrackWithPermissions($track);
    }, array_values($music));
    echo json_encode(['success' => true, 'data' => $music]);
}

function handleMusicUpload(Database $db): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'Method not allowed']]);
        return;
    }

    if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => ['code' => 'UPLOAD_ERROR', 'message' => 'No file uploaded']]);
        return;
    }

    $file = $_FILES['file'];
    $maxSize = 25 * 1024 * 1024; // 25MB
    if (($file['size'] ?? 0) > $maxSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => ['code' => 'FILE_TOO_LARGE', 'message' => 'File exceeds 25MB limit']]);
        return;
    }

    $mime = $file['type'] ?? '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
    }
    $allowed = [
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/wave' => 'wav',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'video/mp4' => 'm4a'
    ];

    if (!isset($allowed[$mime])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_TYPE', 'message' => 'Only mp3, wav, or m4a files are allowed']]);
        return;
    }

    ensurePomodoroMusicDirectories();

    $id = uniqid('pm_');
    $ext = $allowed[$mime];
    $safeName = preg_replace('/[^a-zA-Z0-9-_ ]/', '', pathinfo($file['name'], PATHINFO_FILENAME));
    $filename = $id . '.' . $ext;
    $destination = POMODORO_SHARED_MUSIC_DIR . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => ['code' => 'UPLOAD_FAILED', 'message' => 'Failed to save upload']]);
        return;
    }

    $music = $db->load('pomodoro_music', true) ?? [];
    $record = [
        'id' => $id,
        'name' => $safeName ?: 'Track',
        'filename' => $filename,
        'mime' => $mime,
        'storage' => 'shared',
        'size' => $file['size'] ?? 0,
        'uploadedAt' => date('Y-m-d H:i:s'),
        'uploadedBy' => (string)(Auth::userId() ?? '')
    ];
    $music[] = $record;
    $db->save('pomodoro_music', $music);
    setSharedTrackName($filename, $record['name']);

    echo json_encode(['success' => true, 'data' => $record]);
}

function handleMusicDelete(Database $db, array $input): void {
    $id = $input['id'] ?? $_POST['id'] ?? '';
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => ['code' => 'MISSING_ID', 'message' => 'Track id required']]);
        return;
    }

    $music = $db->load('pomodoro_music', true) ?? [];
    $index = null;
    foreach ($music as $i => $track) {
        if (($track['id'] ?? '') === $id) {
            $index = $i;
            break;
        }
    }

    if ($index !== null) {
        $track = $music[$index];
        if (!canManageTrack($track)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'You can only delete tracks you uploaded']]);
            return;
        }
        $filePath = resolveTrackFilePath($track);
        if ($filePath !== '' && is_file($filePath)) {
            unlink($filePath);
        }

        array_splice($music, $index, 1);
        $db->save('pomodoro_music', $music);
        echo json_encode(['success' => true, 'data' => ['id' => $id]]);
        return;
    }

    // Support deleting bundled/shared tracks discovered from shared folder.
    $bundledTrack = findBundledSharedTrackById($id);
    if ($bundledTrack && !empty($bundledTrack['filename'])) {
        if (!Auth::isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Shared tracks can only be deleted by admin']]);
            return;
        }
        $filePath = POMODORO_SHARED_MUSIC_DIR . '/' . $bundledTrack['filename'];
        if (is_file($filePath)) {
            unlink($filePath);
            echo json_encode(['success' => true, 'data' => ['id' => $id]]);
            return;
        }
    }

    http_response_code(404);
    echo json_encode(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Track not found']]);
}

function handleMusicDownload(Database $db): void {
    $id = $_GET['id'] ?? '';
    if (!$id) {
        http_response_code(400);
        echo 'Missing id';
        return;
    }

    $music = $db->load('pomodoro_music', true) ?? [];
    $track = null;
    foreach ($music as $item) {
        if (($item['id'] ?? '') === $id) {
            $track = $item;
            break;
        }
    }

    if (!$track) {
        $bundled = findBundledSharedTrackById((string)$id);
        if ($bundled) {
            $filename = (string)($bundled['filename'] ?? '');
            $extension = strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));
            $track = [
                'filename' => $filename,
                'mime' => $extension === 'wav' ? 'audio/wav' : ($extension === 'm4a' ? 'audio/mp4' : 'audio/mpeg'),
                'storage' => 'shared'
            ];
        }
    }

    if (!$track) {
        http_response_code(404);
        echo 'Not found';
        return;
    }

    $filePath = resolveTrackFilePath($track);
    if (!is_file($filePath)) {
        http_response_code(404);
        echo 'File missing';
        return;
    }

    $mime = normalizeAudioMime((string)($track['mime'] ?? ''), (string)($track['filename'] ?? ''));
    $size = filesize($filePath);
    if ($size === false) {
        http_response_code(500);
        echo 'Failed to read file';
        return;
    }

    if (ob_get_level() > 0) {
        @ob_end_clean();
    }

    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename((string)$track['filename']) . '"');
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=86400');

    $rangeHeader = $_SERVER['HTTP_RANGE'] ?? '';
    if ($rangeHeader !== '' && preg_match('/bytes=(\d*)-(\d*)/i', $rangeHeader, $matches)) {
        $start = $matches[1] === '' ? 0 : (int)$matches[1];
        $end = $matches[2] === '' ? ($size - 1) : (int)$matches[2];

        if ($start < 0 || $start >= $size || $end < $start) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            return;
        }
        if ($end >= $size) {
            $end = $size - 1;
        }

        $length = ($end - $start) + 1;
        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        header('Content-Length: ' . $length);

        $fp = fopen($filePath, 'rb');
        if ($fp === false) {
            http_response_code(500);
            echo 'Failed to open file';
            return;
        }
        fseek($fp, $start);
        $remaining = $length;
        while ($remaining > 0 && !feof($fp)) {
            $chunkSize = $remaining > 8192 ? 8192 : $remaining;
            $buffer = fread($fp, $chunkSize);
            if ($buffer === false) {
                break;
            }
            echo $buffer;
            $remaining -= strlen($buffer);
            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }
        }
        fclose($fp);
        return;
    }

    header('Content-Length: ' . $size);
    readfile($filePath);
}

function normalizeAudioMime(string $mime, string $filename): string {
    $normalized = strtolower(trim($mime));
    if ($normalized === 'audio/mp3') {
        return 'audio/mpeg';
    }
    if ($normalized === 'audio/x-wav' || $normalized === 'audio/wave') {
        return 'audio/wav';
    }
    if ($normalized === 'audio/x-m4a' || $normalized === 'video/mp4') {
        return 'audio/mp4';
    }
    if ($normalized !== '') {
        return $normalized;
    }

    $ext = strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext === 'wav') {
        return 'audio/wav';
    }
    if ($ext === 'm4a') {
        return 'audio/mp4';
    }
    return 'audio/mpeg';
}

function handleMusicRename(Database $db, array $input): void {
    $id = $input['id'] ?? '';
    $name = trim($input['name'] ?? '');

    if (!$id || !$name) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => ['code' => 'MISSING_PARAMS', 'message' => 'Track id and name required']]);
        return;
    }

    $music = $db->load('pomodoro_music', true) ?? [];
    $found = false;

    foreach ($music as $i => $track) {
        if (($track['id'] ?? '') === $id) {
            if (!canManageTrack($track)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'You can only rename tracks you uploaded']]);
                return;
            }
            $music[$i]['name'] = $name;
            $found = true;
            break;
        }
    }

    if (!$found) {
        $sharedTrack = findBundledSharedTrackById($id);
        if ($sharedTrack) {
            if (!Auth::isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Shared tracks can only be renamed by admin']]);
                return;
            }
            setSharedTrackName((string)$sharedTrack['filename'], $name);
            echo json_encode(['success' => true, 'data' => ['id' => $id, 'name' => $name]]);
            return;
        }

        http_response_code(404);
        echo json_encode(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Track not found']]);
        return;
    }

    $db->save('pomodoro_music', $music);
    $track = findFirstTrackById($music, $id);
    if ($track !== null && (($track['storage'] ?? '') === 'shared')) {
        setSharedTrackName((string)($track['filename'] ?? ''), $name);
    }
    echo json_encode(['success' => true, 'data' => ['id' => $id, 'name' => $name]]);
}

function ensurePomodoroMusicDirectories(): void {
    if (!is_dir(POMODORO_SHARED_MUSIC_DIR)) {
        mkdir(POMODORO_SHARED_MUSIC_DIR, 0755, true);
    }
    if (!is_dir(POMODORO_LEGACY_MUSIC_DIR)) {
        mkdir(POMODORO_LEGACY_MUSIC_DIR, 0755, true);
    }
    if (!is_file(POMODORO_SHARED_MUSIC_METADATA)) {
        file_put_contents(POMODORO_SHARED_MUSIC_METADATA, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

function resolveTrackFilePath(array $track): string {
    $filename = (string)($track['filename'] ?? '');
    if ($filename === '') {
        return '';
    }

    $storage = strtolower(trim((string)($track['storage'] ?? '')));
    if ($storage === 'legacy') {
        $legacyPath = POMODORO_LEGACY_MUSIC_DIR . '/' . $filename;
        if (is_file($legacyPath)) {
            return $legacyPath;
        }
    } else {
        $sharedPath = POMODORO_SHARED_MUSIC_DIR . '/' . $filename;
        if (is_file($sharedPath)) {
            return $sharedPath;
        }
    }

    $fallbackShared = POMODORO_SHARED_MUSIC_DIR . '/' . $filename;
    if (is_file($fallbackShared)) {
        return $fallbackShared;
    }
    $fallbackLegacy = POMODORO_LEGACY_MUSIC_DIR . '/' . $filename;
    if (is_file($fallbackLegacy)) {
        return $fallbackLegacy;
    }

    return '';
}

function migrateLegacyMusicToSharedDir(Database $db): void {
    ensurePomodoroMusicDirectories();
    $music = $db->load('pomodoro_music', true) ?? [];
    $hasChanges = false;

    foreach ($music as $i => $track) {
        $filename = (string)($track['filename'] ?? '');
        if ($filename === '') {
            continue;
        }
        $legacyPath = POMODORO_LEGACY_MUSIC_DIR . '/' . $filename;
        $sharedPath = POMODORO_SHARED_MUSIC_DIR . '/' . $filename;

        if (is_file($legacyPath) && !is_file($sharedPath)) {
            copy($legacyPath, $sharedPath);
        }
        if (is_file($sharedPath) && (($track['storage'] ?? '') !== 'shared')) {
            $music[$i]['storage'] = 'shared';
            $hasChanges = true;
        }
    }

    if ($hasChanges) {
        $db->save('pomodoro_music', $music);
    }
}

function mergeBundledSharedTracks(array $existingTracks): array {
    ensurePomodoroMusicDirectories();
    $metadata = loadSharedTrackMetadata();
    $trackById = [];
    $knownFilenames = [];

    foreach ($existingTracks as $track) {
        $id = (string)($track['id'] ?? '');
        $filename = (string)($track['filename'] ?? '');
        if ($id !== '') {
            $trackById[$id] = $track;
        }
        if ($filename !== '') {
            $knownFilenames[$filename] = true;
        }
    }

    $supportedExtensions = ['mp3', 'wav', 'm4a'];
    $files = scandir(POMODORO_SHARED_MUSIC_DIR) ?: [];
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $extension = strtolower((string)pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($extension, $supportedExtensions, true)) {
            continue;
        }
        if (isset($knownFilenames[$file])) {
            continue;
        }

        $id = 'shared_' . md5('pomodoro:' . $file);
        $mime = $extension === 'wav' ? 'audio/wav' : ($extension === 'm4a' ? 'audio/mp4' : 'audio/mpeg');
        $trackById[$id] = [
            'id' => $id,
            'name' => $metadata[$file]['name'] ?? (string)pathinfo($file, PATHINFO_FILENAME),
            'filename' => $file,
            'mime' => $mime,
            'storage' => 'shared',
            'size' => (int)(@filesize(POMODORO_SHARED_MUSIC_DIR . '/' . $file) ?: 0),
            'uploadedAt' => date('Y-m-d H:i:s', (int)(@filemtime(POMODORO_SHARED_MUSIC_DIR . '/' . $file) ?: time()))
        ];
    }

    return array_values($trackById);
}

function findBundledSharedTrackById(string $trackId): ?array {
    if (!str_starts_with($trackId, 'shared_')) {
        return null;
    }
    $files = scandir(POMODORO_SHARED_MUSIC_DIR) ?: [];
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $candidateId = 'shared_' . md5('pomodoro:' . $file);
        if ($candidateId === $trackId) {
            return [
                'id' => $candidateId,
                'filename' => $file
            ];
        }
    }
    return null;
}

function handleSharedMusicList(): void {
    $tracks = mergeBundledSharedTracks([]);
    usort($tracks, static function(array $a, array $b): int {
        return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });
    echo json_encode(['success' => true, 'data' => $tracks]);
}

function handleSharedMusicUpload(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'Method not allowed']]);
        return;
    }

    if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => ['code' => 'UPLOAD_ERROR', 'message' => 'No file uploaded']]);
        return;
    }

    $file = $_FILES['file'];
    $maxSize = 25 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => ['code' => 'FILE_TOO_LARGE', 'message' => 'File exceeds 25MB limit']]);
        return;
    }

    $mime = $file['type'] ?? '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
    }
    $allowed = [
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/wave' => 'wav',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'video/mp4' => 'm4a'
    ];
    if (!isset($allowed[$mime])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_TYPE', 'message' => 'Only mp3, wav, or m4a files are allowed']]);
        return;
    }

    ensurePomodoroMusicDirectories();
    $baseName = preg_replace('/[^a-zA-Z0-9-_ ]/', '', pathinfo((string)$file['name'], PATHINFO_FILENAME));
    $displayName = trim((string)($_POST['name'] ?? ''));
    $filename = uniqid('shared_', true);
    $filename = str_replace('.', '', $filename) . '.' . $allowed[$mime];
    $destination = POMODORO_SHARED_MUSIC_DIR . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => ['code' => 'UPLOAD_FAILED', 'message' => 'Failed to save upload']]);
        return;
    }

    $resolvedName = $displayName !== '' ? $displayName : ($baseName !== '' ? $baseName : 'Shared Track');
    setSharedTrackName($filename, $resolvedName);

    $trackId = 'shared_' . md5('pomodoro:' . $filename);
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $trackId,
            'name' => $resolvedName,
            'filename' => $filename,
            'mime' => normalizeAudioMime($mime, $filename),
            'storage' => 'shared',
            'size' => (int)($file['size'] ?? 0),
            'uploadedAt' => date('Y-m-d H:i:s')
        ]
    ]);
}

function handleSharedMusicDelete(array $input): void {
    $id = (string)($input['id'] ?? $_POST['id'] ?? '');
    if ($id === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => ['code' => 'MISSING_ID', 'message' => 'Track id required']]);
        return;
    }

    $track = findBundledSharedTrackById($id);
    if ($track === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Track not found']]);
        return;
    }

    $filename = (string)($track['filename'] ?? '');
    $path = POMODORO_SHARED_MUSIC_DIR . '/' . $filename;
    if (is_file($path)) {
        unlink($path);
    }
    removeSharedTrackMetadata($filename);

    echo json_encode(['success' => true, 'data' => ['id' => $id]]);
}

function handleSharedMusicRename(array $input): void {
    $id = (string)($input['id'] ?? '');
    $name = trim((string)($input['name'] ?? ''));
    if ($id === '' || $name === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => ['code' => 'MISSING_PARAMS', 'message' => 'Track id and name required']]);
        return;
    }

    $track = findBundledSharedTrackById($id);
    if ($track === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Track not found']]);
        return;
    }

    setSharedTrackName((string)($track['filename'] ?? ''), $name);
    echo json_encode(['success' => true, 'data' => ['id' => $id, 'name' => $name]]);
}

function loadSharedTrackMetadata(): array {
    ensurePomodoroMusicDirectories();
    $content = file_get_contents(POMODORO_SHARED_MUSIC_METADATA);
    $data = json_decode($content ?: '[]', true);
    return is_array($data) ? $data : [];
}

function saveSharedTrackMetadata(array $metadata): void {
    ensurePomodoroMusicDirectories();
    file_put_contents(POMODORO_SHARED_MUSIC_METADATA, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function setSharedTrackName(string $filename, string $name): void {
    if ($filename === '') {
        return;
    }
    $metadata = loadSharedTrackMetadata();
    $metadata[$filename] = [
        'name' => $name,
        'updatedAt' => date('Y-m-d H:i:s')
    ];
    saveSharedTrackMetadata($metadata);
}

function removeSharedTrackMetadata(string $filename): void {
    if ($filename === '') {
        return;
    }
    $metadata = loadSharedTrackMetadata();
    unset($metadata[$filename]);
    saveSharedTrackMetadata($metadata);
}

function pruneMissingTracks(Database $db, array $tracks): array {
    $filtered = [];
    $changed = false;

    foreach ($tracks as $track) {
        $filePath = resolveTrackFilePath($track);
        if ($filePath === '' || !is_file($filePath)) {
            $changed = true;
            continue;
        }
        $filtered[] = $track;
    }

    if ($changed) {
        $db->save('pomodoro_music', $filtered);
    }

    return $filtered;
}

function findFirstTrackById(array $tracks, string $id): ?array {
    foreach ($tracks as $track) {
        if (($track['id'] ?? '') === $id) {
            return $track;
        }
    }
    return null;
}

function canManageTrack(array $track): bool {
    if (Auth::isAdmin()) {
        return true;
    }
    $ownerId = (string)($track['uploadedBy'] ?? '');
    $userId = (string)(Auth::userId() ?? '');
    if ($ownerId === '' || $userId === '') {
        return false;
    }
    return hash_equals($ownerId, $userId);
}

function decorateTrackWithPermissions(array $track): array {
    $canManage = canManageTrack($track);
    $track['canDelete'] = $canManage;
    $track['canRename'] = $canManage;
    return $track;
}

