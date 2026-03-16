<?php
// API endpoint for favicon management
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/data/php_error.log');

require_once '../config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$action = $_GET['action'] ?? '';

// Check authentication
if (!Auth::check()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$faviconDir = dirname(__DIR__) . '/assets/favicons/';

if ($action === 'upload') {
    // Check if file was uploaded
    if (!isset($_FILES['favicon']) || $_FILES['favicon']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'Upload failed';
        if (isset($_FILES['favicon']['error'])) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File too large (PHP limit)',
                UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
                UPLOAD_ERR_PARTIAL => 'Upload was interrupted',
                UPLOAD_ERR_NO_FILE => 'No file selected',
            ];
            $errorMsg = $errors[$_FILES['favicon']['error']] ?? 'Upload failed';
        }
        echo json_encode(['success' => false, 'error' => $errorMsg]);
        exit;
    }

    $file = $_FILES['favicon'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Validate file type
    $allowed = ['png', 'jpg', 'jpeg', 'ico', 'svg'];
    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: PNG, JPG, ICO, SVG']);
        exit;
    }

    // Validate it's an image (SVG needs special handling)
    if ($ext === 'svg') {
        // Validate SVG by checking content
        $content = file_get_contents($file['tmp_name']);
        if (strpos($content, '<svg') === false) {
            echo json_encode(['success' => false, 'error' => 'File must be a valid SVG']);
            exit;
        }
    } else {
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF])) {
            echo json_encode(['success' => false, 'error' => 'File must be a valid image']);
            exit;
        }
    }

    try {
        $source = $file['tmp_name'];

        // Handle SVG uploads specially
        if ($ext === 'svg') {
            // Save SVG file
            $svgContent = file_get_contents($source);
            file_put_contents($faviconDir . 'favicon.svg', $svgContent);

            // Try to generate PNGs from SVG using ImageMagick or GD with conversion
            $pngGenerated = false;

            // Try ImageMagick if available
            if (extension_loaded('imagick')) {
                try {
                    $imagick = new Imagick();
                    $imagick->readImageBlob($svgContent);
                    $imagick->setImageFormat('png');

                    $sizes = [
                        ['w' => 16, 'h' => 16, 'name' => 'favicon-16x16.png'],
                        ['w' => 32, 'h' => 32, 'name' => 'favicon-32x32.png'],
                        ['w' => 180, 'h' => 180, 'name' => 'apple-touch-icon.png'],
                    ];

                    foreach ($sizes as $size) {
                        $imagick->resizeImage($size['w'], $size['h'], Imagick::FILTER_LANCZOS, 1);
                        $imagick->writeImage($faviconDir . $size['name']);
                    }

                    $imagick->clear();
                    $imagick->destroy();
                    $pngGenerated = true;
                } catch (Exception $e) {
                    // ImageMagick failed, will use fallback
                }
            }

            // If ImageMagick didn't work, generate default four-square PNGs
            if (!$pngGenerated && extension_loaded('gd')) {
                generateDefaultFavicons($faviconDir);
            }

            // Create ICO from the 32x32 PNG
            if (file_exists($faviconDir . 'favicon-32x32.png')) {
                createIcoFile($faviconDir . 'favicon.ico', $faviconDir . 'favicon-32x32.png');
            }
        }
        // Handle PNG/JPG uploads with GD
        elseif (extension_loaded('gd')) {
            $sourceImg = @imagecreatefromstring(file_get_contents($source));
            if ($sourceImg) {
                $sizes = [
                    ['w' => 16, 'h' => 16, 'name' => 'favicon-16x16.png'],
                    ['w' => 32, 'h' => 32, 'name' => 'favicon-32x32.png'],
                    ['w' => 180, 'h' => 180, 'name' => 'apple-touch-icon.png'],
                ];

                foreach ($sizes as $size) {
                    $img = imagecreatetruecolor($size['w'], $size['h']);
                    imagecopyresampled($img, $sourceImg, 0, 0, 0, 0, $size['w'], $size['h'], imagesx($sourceImg), imagesy($sourceImg));
                    imagepng($img, $faviconDir . $size['name']);
                    imagedestroy($img);
                }

                imagedestroy($sourceImg);

                // Create ICO from 32x32 PNG
                createIcoFile($faviconDir . 'favicon.ico', $faviconDir . 'favicon-32x32.png');

                // Remove SVG since we have PNG favicon
                if (file_exists($faviconDir . 'favicon.svg')) {
                    unlink($faviconDir . 'favicon.svg');
                }
            } else {
                // Fallback: just copy the file
                copy($source, $faviconDir . 'favicon-32x32.png');
                copy($source, $faviconDir . 'apple-touch-icon.png');
                copy($source, $faviconDir . 'favicon.ico');
            }
        } else {
            // No GD - just copy the original file
            copy($source, $faviconDir . 'favicon-32x32.png');
            copy($source, $faviconDir . 'apple-touch-icon.png');
            copy($source, $faviconDir . 'favicon.ico');
        }

        // Save theme color and customFavicon flag if provided
        try {
            $masterPassword = function_exists('getMasterPassword') ? getMasterPassword() : ($_SESSION[SESSION_MASTER_KEY] ?? '');
            if (!empty($masterPassword)) {
                $db = new Database($masterPassword, Auth::userId());
                $config = $db->load('config', true);

                // Mark favicon as custom
                $config['customFavicon'] = true;

                // Save theme color if provided
                if (!empty($_POST['theme_color']) && preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['theme_color'])) {
                    $config['faviconThemeColor'] = $_POST['theme_color'];
                }

                $db->save('config', $config);
            }
        } catch (Exception $e) {
            error_log('Favicon config save error: ' . $e->getMessage());
            // Continue without failing - favicon was still uploaded
        }

        echo json_encode(['success' => true, 'message' => 'Favicon uploaded successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error saving file: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'reset') {
    try {
        $masterPassword = function_exists('getMasterPassword') ? getMasterPassword() : ($_SESSION[SESSION_MASTER_KEY] ?? '');
        if (!empty($masterPassword)) {
            $db = new Database($masterPassword, Auth::userId());
            $config = $db->load('config', true);
            unset($config['faviconThemeColor']);
            $config['customFavicon'] = false;  // Mark as default
            $db->save('config', $config);
        }

        // Generate four-square logo favicon (matching mobile app design)
        if (extension_loaded('gd')) {
            $sizes = [
                ['w' => 16, 'h' => 16],
                ['w' => 32, 'h' => 32],
                ['w' => 180, 'h' => 180],
            ];

            foreach ($sizes as $size) {
                $w = $size['w'];
                $h = $size['h'];
                $img = imagecreatetruecolor($w, $h);

                // Colors (matching mobile: black background, white squares)
                $black = imagecolorallocate($img, 0, 0, 0);
                $white = imagecolorallocate($img, 255, 255, 255);
                $white50 = imagecolorallocate($img, 128, 128, 128); // 50% opacity white

                // Fill background with black
                imagefilledrectangle($img, 0, 0, $w, $h, $black);

                // Calculate grid dimensions
                $centerX = $w / 2;
                $centerY = $h / 2;
                $gap = max(1, round($w * 0.04)); // Small gap between squares
                $squareSize = ($w - $gap * 3) / 2; // Two squares + gap on each side

                // Top-left: Full white (d="M24 4H6V17.3333H24V4Z")
                $tlX = $gap;
                $tlY = $gap;
                imagefilledrectangle($img, $tlX, $tlY, $tlX + $squareSize, $tlY + $squareSize, $white);

                // Bottom-right: Full white (d="M42 30.6667H24V44H42V30.6667Z")
                $brX = $centerX + $gap / 2;
                $brY = $centerY + $gap / 2;
                imagefilledrectangle($img, $brX, $brY, $brX + $squareSize, $brY + $squareSize, $white);

                // Top-right: 50% white (d="M24 17.3333H42V30.6667H24V17.3333Z" opacity="0.5")
                $trX = $centerX + $gap / 2;
                $trY = $gap;
                imagefilledrectangle($img, $trX, $trY, $trX + $squareSize, $trY + $squareSize, $white50);

                // Bottom-left: 50% white (d="M6 17.3333V30.6667H24V17.3333H6Z" opacity="0.5")
                $blX = $gap;
                $blY = $centerY + $gap / 2;
                imagefilledrectangle($img, $blX, $blY, $blX + $squareSize, $blY + $squareSize, $white50);

                $filename = $size['w'] === 16 ? 'favicon-16x16.png' : ($size['w'] === 32 ? 'favicon-32x32.png' : 'apple-touch-icon.png');
                imagepng($img, $faviconDir . $filename);
                imagedestroy($img);
            }

            // Create ICO file (basic ICO header + PNG data)
            createIcoFile($faviconDir . 'favicon.ico', $faviconDir . 'favicon-32x32.png');

            // Create the default favicon.svg file
            $defaultSvg = '<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
  <rect width="48" height="48" fill="#000000"/>
  <rect x="2" y="2" width="21" height="21" fill="#ffffff"/>
  <rect x="25" y="25" width="21" height="21" fill="#ffffff"/>
  <rect x="25" y="2" width="21" height="21" fill="#808080"/>
  <rect x="2" y="25" width="21" height="21" fill="#808080"/>
</svg>';
            file_put_contents($faviconDir . 'favicon.svg', $defaultSvg);
        }

        echo json_encode(['success' => true, 'message' => 'Favicon reset to default']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Default: unknown action
echo json_encode(['success' => false, 'error' => 'Unknown action']);

/**
 * Generate default four-square favicon PNGs (matching mobile login design)
 */
function generateDefaultFavicons(string $faviconDir): void {
    $sizes = [
        ['w' => 16, 'h' => 16, 'name' => 'favicon-16x16.png'],
        ['w' => 32, 'h' => 32, 'name' => 'favicon-32x32.png'],
        ['w' => 180, 'h' => 180, 'name' => 'apple-touch-icon.png'],
    ];

    foreach ($sizes as $size) {
        $w = $size['w'];
        $h = $size['h'];
        $img = imagecreatetruecolor($w, $h);

        // Colors (matching mobile: black background, white squares)
        $black = imagecolorallocate($img, 0, 0, 0);
        $white = imagecolorallocate($img, 255, 255, 255);
        $white50 = imagecolorallocate($img, 128, 128, 128); // 50% opacity white

        // Fill background with black
        imagefilledrectangle($img, 0, 0, $w, $h, $black);

        // Calculate grid dimensions
        $centerX = $w / 2;
        $centerY = $h / 2;
        $gap = max(1, round($w * 0.04)); // Small gap between squares
        $squareSize = ($w - $gap * 3) / 2; // Two squares + gap on each side

        // Top-left: Full white
        $tlX = $gap;
        $tlY = $gap;
        imagefilledrectangle($img, $tlX, $tlY, $tlX + $squareSize, $tlY + $squareSize, $white);

        // Bottom-right: Full white
        $brX = $centerX + $gap / 2;
        $brY = $centerY + $gap / 2;
        imagefilledrectangle($img, $brX, $brY, $brX + $squareSize, $brY + $squareSize, $white);

        // Top-right: 50% white
        $trX = $centerX + $gap / 2;
        $trY = $gap;
        imagefilledrectangle($img, $trX, $trY, $trX + $squareSize, $trY + $squareSize, $white50);

        // Bottom-left: 50% white
        $blX = $gap;
        $blY = $centerY + $gap / 2;
        imagefilledrectangle($img, $blX, $blY, $blX + $squareSize, $blY + $squareSize, $white50);

        imagepng($img, $faviconDir . $size['name']);
        imagedestroy($img);
    }
}

/**
 * Create a proper ICO file from PNG
 * ICO format: header (6 bytes) + directory entry (16 bytes) + image data
 */
function createIcoFile(string $icoPath, string $pngPath): void {
    if (!file_exists($pngPath)) {
        // Fallback to simple copy if PNG doesn't exist
        return;
    }

    $pngData = file_get_contents($pngPath);
    $pngSize = strlen($pngData);

    // ICO header: reserved (2) + type (2) + count (2) = 6 bytes
    $header = pack('v', 0);      // Reserved
    $header .= pack('v', 1);     // Type: 1 = icon
    $header .= pack('v', 1);     // Count: 1 image

    // Directory entry: width (1) + height (1) + colors (1) + reserved (1) + planes (2) + bpp (2) + size (4) + offset (4) = 16 bytes
    $width = 32;  // Standard favicon size
    $height = 32;
    $entry = pack('C', $width);
    $entry .= pack('C', $height);
    $entry .= pack('C', 0);      // Colors (0 = more than 256)
    $entry .= pack('C', 0);      // Reserved
    $entry .= pack('v', 1);      // Color planes
    $entry .= pack('v', 32);     // Bits per pixel
    $entry .= pack('V', $pngSize); // Size of image data
    $entry .= pack('V', 22);     // Offset to image data (6 + 16)

    // Write ICO file
    file_put_contents($icoPath, $header . $entry . $pngData);
}

