<?php
/**
 * LazyMan TaskManager - Server & Scheduler Manager
 *
 * This script manages both the PHP built-in web server and
 * the continuous scheduler loop.
 */

// Configuration
define('PHP_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'php.exe');
define('SERVER_PORT', 4041);
define('SERVER_PID_FILE', __DIR__ . DIRECTORY_SEPARATOR . 'server.pid');
define('SCHEDULER_PID_FILE', __DIR__ . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'scheduler.pid');
define('SCHEDULER_SCRIPT', __DIR__ . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'scheduler_simple.php');

class ServerManager
{
    private string $phpPath;
    private string $port;
    private int $serverPid = 0;
    private int $schedulerPid = 0;
    private bool $running = true;

    public function __construct(string $phpPath, string $port = '8000')
    {
        $this->phpPath = $phpPath;
        $this->port = $port;
    }

    public function run(): void
    {
        $this->displayHeader();
        $this->cleanup();
        $this->startScheduler();
        $this->startServer();
        $this->openBrowser();
        $this->monitorProcesses();
    }

    private function displayHeader(): void
    {
        echo "\n";
        echo "╔═══════════════════════════════════════════════════════╗\n";
        echo "║     LazyMan TaskManager - Server + Scheduler          ║\n";
        echo "╚═══════════════════════════════════════════════════════╝\n\n";
    }

    public function cleanup(): void
    {
        echo "Checking for orphaned processes...\n";

        $pidFiles = [
            'server' => SERVER_PID_FILE,
            'scheduler' => SCHEDULER_PID_FILE
        ];

        foreach ($pidFiles as $name => $pidFile) {
            if (file_exists($pidFile)) {
                $pid = (int)trim(file_get_contents($pidFile));
                if ($this->isProcessRunning($pid)) {
                    echo "  Killing orphaned $name (PID: $pid)...\n";
                    $this->killProcess($pid);
                }
                unlink($pidFile);
            }
        }

        echo "  ✓ Cleanup complete\n\n";
    }

    private function isProcessRunning(int $pid): bool
    {
        // Windows process check
        $output = [];
        exec("tasklist /FI \"PID eq $pid\" 2>NUL", $output);
        return count($output) > 1;
    }

    private function killProcess(int $pid): bool
    {
        exec("taskkill /F /PID $pid 2>NUL", $output, $returnCode);
        return $returnCode === 0;
    }

    public function startScheduler(): int
    {
        echo "Starting scheduler...\n";

        $cmd = sprintf(
            '%s %s',
            escapeshellarg($this->phpPath),
            escapeshellarg(SCHEDULER_SCRIPT)
        );

        $logFile = __DIR__ . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'scheduler.log';
        $descriptors = [
            0 => ['pipe', 'r'],                // stdin
            1 => ['file', $logFile, 'a'],      // stdout
            2 => ['file', $logFile, 'a']       // stderr
        ];

        $process = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            die("Failed to start scheduler\n");
        }

        $status = proc_get_status($process);
        $this->schedulerPid = $status['pid'];

        // Write PID to file
        file_put_contents(SCHEDULER_PID_FILE, $this->schedulerPid);

        echo "  ✓ Scheduler started (PID: {$this->schedulerPid})\n";
        echo "  ✓ Logs: cron/scheduler.log\n\n";
        return $this->schedulerPid;
    }

    public function startServer(): int
    {
        echo "Starting web server on localhost:{$this->port}...\n";

        $cmd = sprintf(
            '%s -S localhost:%s -t %s',
            escapeshellarg($this->phpPath),
            $this->port,
            escapeshellarg(__DIR__)
        );

        $logFile = __DIR__ . DIRECTORY_SEPARATOR . 'php_server.log';
        $descriptors = [
            0 => ['pipe', 'r'],                // stdin
            1 => ['file', $logFile, 'a'],      // stdout
            2 => ['file', $logFile, 'a']       // stderr
        ];

        $process = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            die("Failed to start server\n");
        }

        $status = proc_get_status($process);
        $this->serverPid = $status['pid'];

        // Write PID to file
        file_put_contents(SERVER_PID_FILE, $this->serverPid);

        echo "  ✓ Server started (PID: {$this->serverPid})\n";
        echo "  ✓ Logs: php_server.log\n\n";
        return $this->serverPid;
    }

    public function openBrowser(): void
    {
        $url = "http://localhost:{$this->port}";
        echo "Opening browser to {$url}...\n";

        // Windows command to open default browser
        pclose(popen("start {$url}", "r"));

        echo "  ✓ Browser opened\n\n";
    }

    public function monitorProcesses(): void
    {
        echo "╔═══════════════════════════════════════════════════════╗\n";
        echo "║  Server is running!                                   ║\n";
        echo "╠══════════════════════════════════════════════════════════╣\n";
        echo "║  URL:      http://localhost:{$this->port}               \n";
        echo "║  Scheduler: Running in background                       \n";
        echo "║                                                          \n";
        echo "║  Press Ctrl+C to stop both server and scheduler         ║\n";
        echo "╚═══════════════════════════════════════════════════════╝\n\n";

        // Monitoring loop
        while ($this->running) {
            // Check scheduler
            if (!$this->isProcessRunning($this->schedulerPid)) {
                echo "[ERROR] Scheduler died, restarting...\n";
                $this->schedulerPid = $this->startScheduler();
            }

            // Check server
            if (!$this->isProcessRunning($this->serverPid)) {
                echo "[ERROR] Server died, shutting down...\n";
                $this->shutdown();
                break;
            }

            // Sleep before next check
            sleep(5);
        }
    }

    public function shutdown(): void
    {
        echo "\nShutting down...\n";

        $this->running = false;

        // Stop server
        if ($this->isProcessRunning($this->serverPid)) {
            echo "  Stopping server...\n";
            $this->killProcess($this->serverPid);
        }

        // Stop scheduler
        if ($this->isProcessRunning($this->schedulerPid)) {
            echo "  Stopping scheduler...\n";
            $this->killProcess($this->schedulerPid);
        }

        // Clean up PID files
        if (file_exists(SERVER_PID_FILE)) {
            unlink(SERVER_PID_FILE);
        }
        if (file_exists(SCHEDULER_PID_FILE)) {
            unlink(SCHEDULER_PID_FILE);
        }

        echo "  ✓ Shutdown complete\n";
    }
}

// Main execution
try {
    $manager = new ServerManager(PHP_PATH, SERVER_PORT);
    $manager->run();
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
