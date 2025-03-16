<?php

declare(strict_types=1);

namespace Keira\Monitor;

/**
 * Configuration for a single monitor
 */
class MonitorConfig
{
    /**
     * Constructor
     */
    public function __construct(
        private string $id,
        private string $url,
        private int $intervalMs,
        private int $timeoutMs,
        private int $expectedStatus,
        private string $expectedContent,
        private int $alertThreshold,
        private bool $ignoreTlsError,
        private bool $isActive
    ) {
    }

    /**
     * Create from array
     */
    public static function fromArray(array $config): self
    {
        return new self(
            $config['id'],
            $config['url'],
            $config['interval_ms'],
            $config['timeout_ms'],
            $config['expected_status'],
            $config['expected_content'],
            $config['alert_threshold'],
            $config['ignore_tls_error'],
            $config['is_active']
        );
    }

    /**
     * Get ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get URL
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Get interval in milliseconds
     */
    public function getIntervalMs(): int
    {
        return $this->intervalMs;
    }

    /**
     * Get timeout in milliseconds
     */
    public function getTimeoutMs(): int
    {
        return $this->timeoutMs;
    }

    /**
     * Get expected HTTP status code
     */
    public function getExpectedStatus(): int
    {
        return $this->expectedStatus;
    }

    /**
     * Get expected content
     */
    public function getExpectedContent(): string
    {
        return $this->expectedContent;
    }

    /**
     * Get alert threshold
     */
    public function getAlertThreshold(): int
    {
        return $this->alertThreshold;
    }

    /**
     * Check if TLS errors should be ignored
     */
    public function shouldIgnoreTlsError(): bool
    {
        return $this->ignoreTlsError;
    }

    /**
     * Check if monitor is active
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Set active status
     */
    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'interval_ms' => $this->intervalMs,
            'timeout_ms' => $this->timeoutMs,
            'expected_status' => $this->expectedStatus,
            'expected_content' => $this->expectedContent,
            'alert_threshold' => $this->alertThreshold,
            'ignore_tls_error' => $this->ignoreTlsError,
            'is_active' => $this->isActive
        ];
    }
}
