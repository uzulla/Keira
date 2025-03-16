<?php

declare(strict_types=1);

namespace Keira\Util;

use Keira\Monitor\MonitorManager;
use Psr\Log\LoggerInterface;
use function Amp\async;
use function Amp\delay;

/**
 * Data retention manager for Keira
 */
class DataRetention
{
    private const CLEANUP_INTERVAL_SECONDS = 300; // 5 minutes
    
    /**
     * Constructor
     */
    public function __construct(
        private MonitorManager $monitorManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Start data retention task
     */
    public function start(): void
    {
        async(function () {
            $this->logger->info("[INFO][APP] Starting data retention task");
            
            while (true) {
                try {
                    // Wait for the cleanup interval
                    yield delay(self::CLEANUP_INTERVAL_SECONDS);
                    
                    // Clean up old data
                    $this->cleanupOldData();
                } catch (\Throwable $e) {
                    $this->logger->error("[ERROR][APP] Error in data retention task: {$e->getMessage()}");
                    
                    // Wait a bit before retrying
                    yield delay(60);
                }
            }
        });
    }

    /**
     * Clean up old data
     */
    private function cleanupOldData(): void
    {
        $this->logger->info("[INFO][APP] Cleaning up monitoring data older than 24 hours");
        $this->monitorManager->cleanOldResults();
    }
}
