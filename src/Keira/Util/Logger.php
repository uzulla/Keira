<?php

declare(strict_types=1);

namespace Keira\Util;

use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

/**
 * Logger factory for Keira
 * 
 * Creates loggers with the required format:
 * - Application logs: [INFO][APP] Application started successfully
 * - Monitor logs: [INFO][MONITOR] service-api-1, OK, 120ms, 200
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
    
    /**
     * Create a logger for application logs
     */
    public static function createAppLogger(string $name = 'keira', string $logFile = 'php://stdout'): LoggerInterface
    {
        $logger = self::create($name, $logFile);
        
        // Add a processor to set the category to APP
        $logger->pushProcessor(function ($record) {
            $context = $record->context;
            $context['category'] = 'APP';
            return $record->with(context: $context);
        });
        
        return $logger;
    }
    
    /**
     * Create a logger for monitor logs
     */
    public static function createMonitorLogger(string $name = 'keira', string $logFile = 'php://stdout'): LoggerInterface
    {
        $logger = self::create($name, $logFile);
        
        // Add a processor to set the category to MONITOR
        $logger->pushProcessor(function ($record) {
            $context = $record->context;
            $context['category'] = 'MONITOR';
            return $record->with(context: $context);
        });
        
        return $logger;
    }
}
