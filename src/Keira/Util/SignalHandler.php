<?php

declare(strict_types=1);

namespace Keira\Util;

use Amp\SignalException;
use Keira\Config\ConfigLoader;
use Keira\Monitor\MonitorManager;
use Psr\Log\LoggerInterface;
use function Amp\async;
use function Amp\trapSignal;

/**
 * Signal handler for Keira
 */
class SignalHandler
{
    /**
     * Constructor
     */
    public function __construct(
        private ConfigLoader $configLoader,
        private MonitorManager $monitorManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Register signal handlers
     */
    public function register(): void
    {
        // SIGHUP: Reload configuration
        async(function () {
            while (true) {
                try {
                    trapSignal(\SIGHUP);
                    $this->handleSighup();
                } catch (SignalException $e) {
                    // Signal handler was cancelled
                    break;
                }
            }
        });

        // SIGUSR1: Pause monitoring
        async(function () {
            while (true) {
                try {
                    trapSignal(\SIGUSR1);
                    $this->handleSigusr1();
                } catch (SignalException $e) {
                    // Signal handler was cancelled
                    break;
                }
            }
        });

        // SIGUSR2: Resume monitoring
        async(function () {
            while (true) {
                try {
                    trapSignal(\SIGUSR2);
                    $this->handleSigusr2();
                } catch (SignalException $e) {
                    // Signal handler was cancelled
                    break;
                }
            }
        });
    }

    /**
     * Handle SIGHUP signal (reload configuration)
     */
    private function handleSighup(): void
    {
        $this->logger->info("[INFO][APP] Received SIGHUP signal, reloading configuration");
        
        try {
            // Reload configuration
            $config = $this->configLoader->reload();
            
            // Update monitor configurations
            $this->monitorManager->configure($config['monitors']);
            
            $this->logger->info("[INFO][APP] Configuration reloaded successfully");
        } catch (\Throwable $e) {
            $this->logger->error("[ERROR][APP] Configuration reload failed: {$e->getMessage()}");
        }
    }

    /**
     * Handle SIGUSR1 signal (pause monitoring)
     */
    private function handleSigusr1(): void
    {
        $this->logger->info("[INFO][APP] Received SIGUSR1 signal, pausing monitoring");
        $this->monitorManager->stop();
    }

    /**
     * Handle SIGUSR2 signal (resume monitoring)
     */
    private function handleSigusr2(): void
    {
        $this->logger->info("[INFO][APP] Received SIGUSR2 signal, resuming monitoring");
        $this->monitorManager->start();
    }
}
