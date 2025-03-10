<?php
class Logger
{
    private $logFile;
    private static $instance = null;

    private function __construct()
    {
        $date = date('Y-m-d');
        $this->logFile = __DIR__ . "/../logs/app-$date.log";
        $this->ensureLogDirectoryExists();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Logger();
        }
        return self::$instance;
    }

    private function ensureLogDirectoryExists()
    {
        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
    }

    public function log($message, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = sprintf(
            "[%s] [%s] %s%s",
            $timestamp,
            strtoupper($level),
            $message,
            PHP_EOL
        );

        error_log($logMessage, 3, $this->logFile);
    }

    public function error($message)
    {
        $this->log($message, 'ERROR');
    }

    public function info($message)
    {
        $this->log($message, 'INFO');
    }

    public function debug($message)
    {
        if (DEBUG_MODE) {
            $this->log($message, 'DEBUG');
        }
    }

    public function warning($message)
    {
        $this->log($message, 'WARNING');
    }

    public function getLogPath()
    {
        return $this->logFile;
    }

    public function clearLog()
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function rotateLogs()
    {
        if (file_exists($this->logFile)) {
            $maxSize = 5 * 1024 * 1024; // 5MB
            if (filesize($this->logFile) > $maxSize) {
                $backup = $this->logFile . '.' . date('Y-m-d-H-i-s') . '.bak';
                rename($this->logFile, $backup);

                // Keep only last 5 backup files
                $backups = glob($this->logFile . '.*.bak');
                if (count($backups) > 5) {
                    usort($backups, function ($a, $b) {
                        return filemtime($a) - filemtime($b);
                    });
                    while (count($backups) > 5) {
                        unlink(array_shift($backups));
                    }
                }
            }
        }
    }
}
