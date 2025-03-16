<?php

declare(strict_types=1);

namespace Tests;

use Amp\Loop;
use Keira\Monitor\MonitorResult;
use Keira\Slack\SlackNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SlackTest extends TestCase
{
    public function testSlackNotifierConstruction(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $notifier = new SlackNotifier(
            'https://hooks.slack.com/services/T00000000/B0000000/XXXXXXXXXXXXXXXXXXXXXXXX',
            '#alerts-channel',
            $logger
        );
        
        $this->assertInstanceOf(SlackNotifier::class, $notifier);
    }
    
    public function testSendAlert(): void
    {
        // Skip actual HTTP requests in tests
        $this->markTestSkipped('Skipping test that would make actual HTTP requests');
        
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())
            ->method('info')
            ->with($this->stringContains('[INFO][APP] Slack notification sent successfully'));
        
        $notifier = new SlackNotifier(
            'https://hooks.slack.com/services/T00000000/B0000000/XXXXXXXXXXXXXXXXXXXXXXXX',
            '#alerts-channel',
            $logger
        );
        
        $result = MonitorResult::createFailure(
            'test-service',
            1500,
            500,
            'Server Error'
        );
        
        $notifier->sendAlert('test-service', 'https://example.com/api', $result, 3);
    }
    
    public function testSendRecovery(): void
    {
        // Skip actual HTTP requests in tests
        $this->markTestSkipped('Skipping test that would make actual HTTP requests');
        
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())
            ->method('info')
            ->with($this->stringContains('[INFO][APP] Slack notification sent successfully'));
        
        $notifier = new SlackNotifier(
            'https://hooks.slack.com/services/T00000000/B0000000/XXXXXXXXXXXXXXXXXXXXXXXX',
            '#alerts-channel',
            $logger
        );
        
        $result = MonitorResult::createSuccess('test-service', 150, 200);
        
        $notifier->sendRecovery('test-service', 'https://example.com/api', $result);
    }
}
