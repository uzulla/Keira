<?php

declare(strict_types=1);

namespace Keira\Util;

use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

/**
 * Logger factory for Keira
 */
class Logger
{
    /**
     * Create a new logger instance
     */
    public static function create(string $name = 'keira', string $logFile = 'php://stdout'): LoggerInterface
    {
        $logger = new MonologLogger($name);
        
        // Add a handler that writes to the specified log file
        $handler = new StreamHandler($logFile);
        
        // Use a custom formatter to match the required log format
        $handler->setFormatter(new LogFormatter());
        
        // Add the handler to the logger
        $logger->pushHandler($handler);
        
        // Add a processor to handle message placeholders
        $logger->pushProcessor(new PsrLogMessageProcessor());
        
        return $logger;
    }
}
