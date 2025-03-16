<?php

declare(strict_types=1);

namespace Keira\Util;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

/**
 * Custom log formatter for Keira
 * 
 * Formats logs according to the required format:
 * - Application logs: [INFO][APP] Application started successfully
 * - Monitor logs: [INFO][MONITOR] service-api-1, OK, 120ms, 200
 */
class LogFormatter implements FormatterInterface
{
    /**
     * Format a log record
     */
    public function format(LogRecord $record): string
    {
        $level = strtoupper($record->level->name);
        $context = $record->context;
        $message = $record->message;
        
        // Determine the log category (APP or MONITOR)
        $category = $context['category'] ?? 'APP';
        
        // Format the log message according to the required format
        return sprintf("[%s][%s] %s\n", $level, $category, $message);
    }

    /**
     * Format a batch of log records
     *
     * @param array<LogRecord> $records
     */
    public function formatBatch(array $records): string
    {
        $formatted = '';
        foreach ($records as $record) {
            $formatted .= $this->format($record);
        }
        
        return $formatted;
    }
}
