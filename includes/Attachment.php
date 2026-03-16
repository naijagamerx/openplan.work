<?php
/**
 * Attachment Class - File attachment management
 *
 * Handles file uploads, storage, and retrieval for task attachments:
 * - Secure file upload with validation
 * - Encrypted storage metadata
 * - File type detection and validation
 * - Size limit enforcement (10MB)
 */

class Attachment {
    protected Database $db;
    protected string $collection = 'attachments';
    protected string $uploadPath;

    // Allowed MIME types
    const ALLOWED_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'text/plain', 'text/csv', 'text/markdown',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip', 'application/x-zip-compressed'
    ];

    // File category mappings
    const CATEGORIES = [
        'image/jpeg' => 'image',
        'image/png' => 'image',
        'image/gif' => 'image',
        'image/webp' => 'image',
        'application/pdf' => 'document',
        'text/plain' => 'text',
        'text/csv' => 'text',
        'text/markdown' => 'text',
        'application/msword' => 'document',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document',
        'application/vnd.ms-excel' => 'spreadsheet',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'spreadsheet',
        'application/zip' => 'archive',
        'application/x-zip-compressed' => 'archive'
    ];

    const MAX_SIZE = 10 * 1024 * 1024; // 10MB

    public function __construct(Database $db) {
        $this->db = $db;
        $this->uploadPath = ROOT_PATH . '/uploads';

        // Create upload directory if it doesn't exist
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }

        // Create task-specific subdirectories
        $taskDir = $this->uploadPath . '/tasks';
        if (!is_dir($taskDir)) {
            mkdir($taskDir, 0755, true);
        }
    }

    /**
     * Upload a file
     */
    public function upload(array $file, string $taskId): array {
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload error: ' . $file['error']);
        }

        if ($file['size'] > self::MAX_SIZE) {
            throw new Exception('File size exceeds maximum allowed size of 10MB');
        }

        // Get MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, self::ALLOWED_TYPES)) {
            throw new Exception('File type not allowed');
        }

        // Generate unique filename
        $extension = $this->getExtension($file['name'], $mimeType);
        $filename = $this->generateFilename($extension);
        $taskDir = $this->uploadPath . '/tasks/' . $taskId;

        // Create task directory
        if (!is_dir($taskDir)) {
            mkdir($taskDir, 0755, true);
        }

        $filepath = $taskDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Failed to move uploaded file');
        }

        // Create attachment record
        $attachment = [
            'id' => $this->generateId(),
            'taskId' => $taskId,
            'filename' => $filename,
            'originalName' => basename($file['name']),
            'mimeType' => $mimeType,
            'category' => self::CATEGORIES[$mimeType] ?? 'other',
            'size' => filesize($filepath),
            'path' => 'tasks/' . $taskId . '/' . $filename,
            'createdAt' => date('c'),
            'createdBy' => Auth::userId() ?? 'system'
        ];

        // Save to database
        $attachments = $this->db->load($this->collection);
        $attachments[] = $attachment;
        $this->db->save($this->collection, $attachments);

        return $attachment;
    }

    /**
     * Get all attachments for a task
     */
    public function getByTaskId(string $taskId): array {
        $attachments = $this->db->load($this->collection);
        $result = [];

        foreach ($attachments as $attachment) {
            if ($attachment['taskId'] === $taskId) {
                // Check if file exists
                $filepath = $this->uploadPath . '/' . $attachment['path'];
                if (file_exists($filepath)) {
                    $attachment['exists'] = true;
                    $attachment['sizeFormatted'] = $this->formatSize($attachment['size']);
                } else {
                    $attachment['exists'] = false;
                }
                $result[] = $attachment;
            }
        }

        // Sort by creation date (newest first)
        usort($result, function($a, $b) {
            return strtotime($b['createdAt']) - strtotime($a['createdAt']);
        });

        return $result;
    }

    /**
     * Get single attachment by ID
     */
    public function getById(string $id): ?array {
        $attachments = $this->db->load($this->collection);

        foreach ($attachments as $attachment) {
            if ($attachment['id'] === $id) {
                // Check if file exists
                $filepath = $this->uploadPath . '/' . $attachment['path'];
                if (file_exists($filepath)) {
                    $attachment['exists'] = true;
                    $attachment['sizeFormatted'] = $this->formatSize($attachment['size']);
                } else {
                    $attachment['exists'] = false;
                }
                return $attachment;
            }
        }

        return null;
    }

    /**
     * Download an attachment
     */
    public function download(string $id): void {
        $attachment = $this->getById($id);

        if (!$attachment || !$attachment['exists']) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        $filepath = $this->uploadPath . '/' . $attachment['path'];

        // Set headers for download
        header('Content-Type: ' . $attachment['mimeType']);
        header('Content-Disposition: attachment; filename="' . $attachment['originalName'] . '"');
        header('Content-Length: ' . $attachment['size']);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        // Serve file
        readfile($filepath);
        exit;
    }

    /**
     * Delete an attachment
     */
    public function delete(string $id): bool {
        $attachment = $this->getById($id);

        if (!$attachment) {
            return false;
        }

        // Delete file
        $filepath = $this->uploadPath . '/' . $attachment['path'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        // Remove from database
        $attachments = $this->db->load($this->collection);
        $newAttachments = array_values(array_filter($attachments, function($a) use ($id) {
            return $a['id'] !== $id;
        }));

        return $this->db->save($this->collection, $newAttachments);
    }

    /**
     * Get file icon based on MIME type
     */
    public function getIcon(string $mimeType): string {
        return match ($mimeType) {
            'image/jpeg', 'image/png', 'image/gif', 'image/webp' => 'image',
            'application/pdf' => 'pdf',
            'text/plain', 'text/csv', 'text/markdown' => 'text',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'word',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'excel',
            'application/zip', 'application/x-zip-compressed' => 'archive',
            default => 'file'
        };
    }

    /**
     * Get storage statistics
     */
    public function getStats(): array {
        $attachments = $this->db->load($this->collection);

        $totalSize = 0;
        $byCategory = [];
        $byMime = [];

        foreach ($attachments as $attachment) {
            $totalSize += $attachment['size'] ?? 0;

            $category = $attachment['category'] ?? 'unknown';
            $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;

            $mime = $attachment['mimeType'] ?? 'unknown';
            $byMime[$mime] = ($byMime[$mime] ?? 0) + 1;
        }

        return [
            'total_attachments' => count($attachments),
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatSize($totalSize),
            'by_category' => $byCategory,
            'by_mime_type' => $byMime
        ];
    }

    /**
     * Generate unique ID
     */
    protected function generateId(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Generate unique filename
     */
    protected function generateFilename(string $extension): string {
        return date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . $extension;
    }

    /**
     * Get file extension from name and MIME type
     */
    protected function getExtension(string $filename, string $mimeType): string {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $extension = strtolower($extension);

        $mimeExtensions = [
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/gif' => '.gif',
            'image/webp' => '.webp',
            'application/pdf' => '.pdf',
            'application/msword' => '.doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
            'application/vnd.ms-excel' => '.xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '.xlsx',
            'application/zip' => '.zip',
            'application/x-zip-compressed' => '.zip',
        ];

        return $mimeExtensions[$mimeType] ?? ($extension ? '.' . $extension : '');
    }

    /**
     * Format bytes to human readable
     */
    protected function formatSize(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
