<?php

declare(strict_types=1);

namespace Keira\Config;

use RuntimeException;

/**
 * Configuration loader for Keira
 */
class ConfigLoader
{
    private string $configPath;
    private array $config = [];
    private bool $isLoaded = false;

    /**
     * Constructor
     */
    public function __construct(string $configPath)
    {
        $this->configPath = $configPath;
    }

    /**
     * Load configuration from file
     *
     * @throws RuntimeException If configuration file is invalid
     */
    public function load(): array
    {
        if (!file_exists($this->configPath)) {
            throw new RuntimeException("Configuration file not found: {$this->configPath}");
        }

        $content = file_get_contents($this->configPath);
        if ($content === false) {
            throw new RuntimeException("Failed to read configuration file: {$this->configPath}");
        }

        $config = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        
        $this->validateConfig($config);
        
        $this->config = $config;
        $this->isLoaded = true;
        
        return $this->config;
    }

    /**
     * Reload configuration
     *
     * @throws RuntimeException If configuration file is invalid
     */
    public function reload(): array
    {
        return $this->load();
    }

    /**
     * Get configuration
     *
     * @throws RuntimeException If configuration is not loaded
     */
    public function getConfig(): array
    {
        if (!$this->isLoaded) {
            throw new RuntimeException("Configuration not loaded");
        }
        
        return $this->config;
    }

    /**
     * Validate configuration
     *
     * @throws RuntimeException If configuration is invalid
     */
    private function validateConfig(array $config): void
    {
        // Validate slack configuration
        if (!isset($config['slack']) || !is_array($config['slack'])) {
            throw new RuntimeException("Invalid configuration: 'slack' section is missing or invalid");
        }

        if (!isset($config['slack']['webhook_url']) || !is_string($config['slack']['webhook_url'])) {
            throw new RuntimeException("Invalid configuration: 'slack.webhook_url' is missing or invalid");
        }

        if (!isset($config['slack']['channel']) || !is_string($config['slack']['channel'])) {
            throw new RuntimeException("Invalid configuration: 'slack.channel' is missing or invalid");
        }

        // Validate monitors configuration
        if (!isset($config['monitors']) || !is_array($config['monitors'])) {
            throw new RuntimeException("Invalid configuration: 'monitors' section is missing or invalid");
        }

        foreach ($config['monitors'] as $index => $monitor) {
            $this->validateMonitorConfig($monitor, $index);
        }
    }

    /**
     * Validate monitor configuration
     *
     * @throws RuntimeException If monitor configuration is invalid
     */
    private function validateMonitorConfig(array $monitor, int $index): void
    {
        $requiredFields = [
            'id' => 'string',
            'url' => 'string',
            'interval_ms' => 'integer',
            'timeout_ms' => 'integer',
            'expected_status' => 'integer',
            'expected_content' => 'string',
            'alert_threshold' => 'integer',
            'ignore_tls_error' => 'boolean',
            'is_active' => 'boolean'
        ];

        foreach ($requiredFields as $field => $type) {
            if (!isset($monitor[$field])) {
                throw new RuntimeException("Invalid monitor configuration at index {$index}: '{$field}' is missing");
            }

            $validationType = match($type) {
                'string' => is_string($monitor[$field]),
                'integer' => is_int($monitor[$field]),
                'boolean' => is_bool($monitor[$field]),
                default => false
            };

            if (!$validationType) {
                throw new RuntimeException("Invalid monitor configuration at index {$index}: '{$field}' should be of type {$type}");
            }
        }

        // Additional validation for specific fields
        if ($monitor['interval_ms'] < 100) {
            throw new RuntimeException("Invalid monitor configuration at index {$index}: 'interval_ms' should be at least 100ms");
        }

        if ($monitor['timeout_ms'] < 100) {
            throw new RuntimeException("Invalid monitor configuration at index {$index}: 'timeout_ms' should be at least 100ms");
        }

        if ($monitor['alert_threshold'] < 1) {
            throw new RuntimeException("Invalid monitor configuration at index {$index}: 'alert_threshold' should be at least 1");
        }
    }
}
