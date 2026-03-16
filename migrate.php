<?php
require_once 'config.php';

// Ensure user is logged in
if (!Auth::check()) {
    header('Location: index.php?page=login&redirect=migrate.php');
    exit;
}

$user = Auth::user();
$userId = $user['id'];
$masterPassword = getMasterPassword();

$pageTitle = 'Data Migration';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrating Data... - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-xl shadow-lg p-8">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Upgrading Account</h1>
            <p class="text-gray-500 mt-2">Moving your data to your private secure folder...</p>
        </div>

        <div class="space-y-4">
            <div class="bg-gray-100 rounded-lg p-4 font-mono text-sm h-48 overflow-y-auto" id="log">
                <div class="text-gray-500">Initializing migration...</div>
            </div>
            
            <div id="status-icon" class="flex justify-center py-4">
                <svg class="animate-spin w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>

            <button id="continue-btn" onclick="window.location.href='index.php'" class="w-full py-3 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition hidden">
                Continue to Dashboard
            </button>
        </div>
    </div>

    <?php
    // Migration Logic
    $log = [];
    $success = true;
    
    try {
        // Create user directory if not exists
        $userDir = DATA_PATH . '/users/' . $userId;
        if (!is_dir($userDir)) {
            if (mkdir($userDir, 0755, true)) {
                $log[] = "Created user directory: users/{$userId}";
            } else {
                throw new Exception("Failed to create user directory");
            }
        } else {
            $log[] = "User directory already exists";
        }

        // Files to move (root -> user dir)
        $files = glob(DATA_PATH . '/*.json.enc');
        $movedCount = 0;
        
        foreach ($files as $file) {
            $filename = basename($file);
            
            // Skip system files
            if (in_array($filename, [
                'users.json.enc', 
                'rate_limits.json', 
                'scheduler_config.json',
                'scheduler_autostart_lock.json',
                'public_config.json'
            ])) {
                continue;
            }
            
            // Check if destination exists
            $dest = $userDir . '/' . $filename;
            if (file_exists($dest)) {
                $log[] = "Skipped {$filename} (already exists)";
                continue;
            }
            
            // Move file
            if (rename($file, $dest)) {
                $log[] = "Moved {$filename}";
                $movedCount++;
            } else {
                $log[] = "Failed to move {$filename}";
                $success = false;
            }
        }
        
        if ($movedCount === 0) {
            $log[] = "No files needed moving.";
        } else {
            $log[] = "Successfully moved {$movedCount} files.";
        }

        // Move Backups
        $backupDir = DATA_PATH . '/backups';
        $userBackupDir = $userDir . '/backups';
        
        if (is_dir($backupDir)) {
            if (!is_dir($userBackupDir)) {
                mkdir($userBackupDir, 0755, true);
            }
            
            $backupFiles = glob($backupDir . '/*');
            $movedBackups = 0;
            
            foreach ($backupFiles as $file) {
                $filename = basename($file);
                $dest = $userBackupDir . '/' . $filename;
                
                if (file_exists($dest)) {
                    continue; // Skip existing
                }
                
                if (rename($file, $dest)) {
                    $movedBackups++;
                }
            }
            
            if ($movedBackups > 0) {
                $log[] = "Moved {$movedBackups} backups.";
            }
        }

    } catch (Exception $e) {
        $log[] = "Error: " . $e->getMessage();
        $success = false;
    }
    ?>

    <script>
        const logEl = document.getElementById('log');
        const logs = <?php echo json_encode($log); ?>;
        const success = <?php echo json_encode($success); ?>;
        
        // Display logs with delay
        let i = 0;
        function showLog() {
            if (i < logs.length) {
                const div = document.createElement('div');
                div.className = logs[i].includes('Error') || logs[i].includes('Failed') ? 'text-red-600' : 'text-green-600';
                div.textContent = '> ' + logs[i];
                logEl.appendChild(div);
                logEl.scrollTop = logEl.scrollHeight;
                i++;
                setTimeout(showLog, 100);
            } else {
                finish();
            }
        }
        
        function finish() {
            const icon = document.getElementById('status-icon');
            const btn = document.getElementById('continue-btn');
            
            if (success) {
                icon.innerHTML = `<svg class="w-12 h-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`;
                btn.classList.remove('hidden');
            } else {
                icon.innerHTML = `<svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>`;
                const retryBtn = document.createElement('button');
                retryBtn.textContent = "Retry";
                retryBtn.className = "w-full py-3 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition mt-2";
                retryBtn.onclick = () => window.location.reload();
                btn.parentNode.insertBefore(retryBtn, btn);
            }
        }
        
        setTimeout(showLog, 500);
    </script>
</body>
</html>
