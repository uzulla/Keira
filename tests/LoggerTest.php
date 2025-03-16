<?php

declare(strict_types=1);

namespace Tests;

use Keira\Util\Logger;
use Keira\Util\LogFormatter;
use Monolog\LogRecord;
use Monolog\Level;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    public function testLoggerCreation(): void
    {
        $logger = Logger::create('test');
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $logger);
    }
    
    public function testLogFormatterFormat(): void
    {
        $formatter = new LogFormatter();
        
        // Test APP log format
        $record = new LogRecord(
            new \DateTimeImmutable(),
            'test',
            Level::Info,
            'Application started successfully',
            ['category' => 'APP']
        );
        
        $formatted = $formatter->format($record);
        $this->assertStringContainsString('[INFO][APP] Application started successfully', $formatted);
        
        // Test MONITOR log format
        $record = new LogRecord(
            new \DateTimeImmutable(),
            'test',
            Level::Info,
            'service-api-1, OK, 120ms, 200',
            ['category' => 'MONITOR']
        );
        
        $formatted = $formatter->format($record);
        $this->assertStringContainsString('[INFO][MONITOR] service-api-1, OK, 120ms, 200', $formatted);
        
        // Test error log format
        $record = new LogRecord(
            new \DateTimeImmutable(),
            'test',
            Level::Error,
            'service-api-1, NG, 99999ms, 500, Invalid Status Code',
            ['category' => 'MONITOR']
        );
        
        $formatted = $formatter->format($record);
        $this->assertStringContainsString('[ERROR][MONITOR] service-api-1, NG, 99999ms, 500, Invalid Status Code', $formatted);
    }
}
