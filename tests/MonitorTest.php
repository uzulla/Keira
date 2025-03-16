<?php

declare(strict_types=1);

namespace Tests;

use Keira\Monitor\MonitorConfig;
use Keira\Monitor\MonitorResult;
use PHPUnit\Framework\TestCase;

class MonitorTest extends TestCase
{
    public function testMonitorConfigFromArray(): void
    {
        $configArray = [
            'id' => 'test-service',
            'url' => 'https://example.com/health',
            'interval_ms' => 500,
            'timeout_ms' => 1000,
            'expected_status' => 200,
            'expected_content' => 'OK',
            'alert_threshold' => 3,
            'ignore_tls_error' => true,
            'is_active' => true
        ];
        
        $config = MonitorConfig::fromArray($configArray);
        
        $this->assertSame('test-service', $config->getId());
        $this->assertSame('https://example.com/health', $config->getUrl());
        $this->assertSame(500, $config->getIntervalMs());
        $this->assertSame(1000, $config->getTimeoutMs());
        $this->assertSame(200, $config->getExpectedStatus());
        $this->assertSame('OK', $config->getExpectedContent());
        $this->assertSame(3, $config->getAlertThreshold());
        $this->assertTrue($config->shouldIgnoreTlsError());
        $this->assertTrue($config->isActive());
    }
    
    public function testMonitorResultSuccess(): void
    {
        $result = MonitorResult::createSuccess('test-service', 150, 200);
        
        $this->assertSame('test-service', $result->getId());
        $this->assertSame('OK', $result->getStatus());
        $this->assertSame(150, $result->getResponseTimeMs());
        $this->assertSame(200, $result->getHttpStatus());
        $this->assertNull($result->getError());
        $this->assertTrue($result->isSuccess());
    }
    
    public function testMonitorResultFailure(): void
    {
        $result = MonitorResult::createFailure('test-service', 300, 500, 'Server Error');
        
        $this->assertSame('test-service', $result->getId());
        $this->assertSame('NG', $result->getStatus());
        $this->assertSame(300, $result->getResponseTimeMs());
        $this->assertSame(500, $result->getHttpStatus());
        $this->assertSame('Server Error', $result->getError());
        $this->assertFalse($result->isSuccess());
    }
    
    public function testMonitorResultToArray(): void
    {
        $result = MonitorResult::createSuccess('test-service', 150, 200);
        $array = $result->toArray();
        
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('timestamp', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('response_time_ms', $array);
        $this->assertArrayHasKey('http_status', $array);
        $this->assertArrayHasKey('error', $array);
        
        $this->assertSame('test-service', $array['id']);
        $this->assertSame('OK', $array['status']);
        $this->assertSame(150, $array['response_time_ms']);
        $this->assertSame(200, $array['http_status']);
        $this->assertNull($array['error']);
    }
}
