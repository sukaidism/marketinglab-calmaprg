<?php
declare(strict_types=1);

namespace Lab;

final class ActivityLogger
{
    private string $logPath;

    public function __construct(?string $logPath = null)
    {
        $this->logPath = $logPath ?? dirname(__DIR__) . '/logs/events.jsonl';
    }

    public function record(string $event, array $metadata = []): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $entry = array_merge(
            [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'event'     => $event,
            ],
            $metadata
        );
        file_put_contents(
            $this->logPath,
            json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
