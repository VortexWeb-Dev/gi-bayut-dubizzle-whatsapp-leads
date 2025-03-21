<?php

class LoggerController
{
    private string $baseLogDir;

    public function __construct()
    {
        $this->baseLogDir = __DIR__ . '/../logs';
        $this->ensureBaseDirectory();
    }

    private function ensureBaseDirectory(): void
    {
        if (!is_dir($this->baseLogDir)) {
            mkdir($this->baseLogDir, 0777, true);
        }
    }

    private function getDateBasedDirectory(): string
    {
        $year = date('Y');
        $month = date('m');
        $day = date('d');

        $datePath = "{$this->baseLogDir}/{$year}/{$month}/{$day}";

        if (!is_dir($datePath)) {
            mkdir($datePath, 0777, true);
        }

        return $datePath;
    }

    public function logRequest(string $route): void
    {
        $logDir = $this->getDateBasedDirectory();
        $logFile = "{$logDir}/requests.log";

        $logEntry = sprintf(
            "[%s] Route: %s, Method: %s, IP: %s, User Agent: %s\n",
            date('Y-m-d H:i:s'),
            $route,
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        );

        $this->writeLog($logFile, $logEntry);
    }

    public function logWebhook(string $type, array $data): void
    {
        $logDir = $this->getDateBasedDirectory();
        $logFile = "{$logDir}/webhooks.log";

        $logEntry = sprintf(
            "[%s] Type: %s, Data: %s\n",
            date('Y-m-d H:i:s'),
            $type,
            json_encode($data, JSON_PRETTY_PRINT)
        );

        $this->writeLog($logFile, $logEntry);
    }

    public function logError(string $message, ?Throwable $exception = null): void
    {
        $logDir = $this->getDateBasedDirectory();
        $logFile = "{$logDir}/errors.log";

        $logEntry = sprintf(
            "[%s] Error: %s\n",
            date('Y-m-d H:i:s'),
            $message
        );

        if ($exception) {
            $logEntry .= sprintf(
                "Exception: %s\nStack Trace:\n%s\n",
                $exception->getMessage(),
                $exception->getTraceAsString()
            );
        }

        $this->writeLog($logFile, $logEntry);
    }

    private function writeLog(string $logFile, string $logEntry): void
    {
        try {
            // Create a file handle with exclusive locking
            $handle = fopen($logFile, 'a');

            if (flock($handle, LOCK_EX)) {
                fwrite($handle, $logEntry);
                fflush($handle);
                flock($handle, LOCK_UN);
            }

            fclose($handle);
        } catch (Throwable $e) {
            error_log("Failed to write to log file {$logFile}: " . $e->getMessage());
            error_log("Original log entry: " . $logEntry);
        }
    }

    public function cleanOldLogs(int $daysToKeep = 30): void
    {
        try {
            $now = time();
            $this->cleanDirectory($this->baseLogDir, $now, $daysToKeep);
        } catch (Throwable $e) {
            error_log("Failed to clean old logs: " . $e->getMessage());
        }
    }

    private function cleanDirectory(string $dir, int $now, int $daysToKeep): void
    {
        $files = new DirectoryIterator($dir);

        foreach ($files as $file) {
            if ($file->isDot()) continue;

            $path = $file->getPathname();

            if ($file->isDir()) {
                $this->cleanDirectory($path, $now, $daysToKeep);

                if (count(glob("$path/*")) === 0) {
                    rmdir($path);
                }
            } else {
                $fileAge = $now - $file->getMTime();
                $daysOld = $fileAge / (60 * 60 * 24);

                if ($daysOld > $daysToKeep) {
                    unlink($path);
                }
            }
        }
    }
}
