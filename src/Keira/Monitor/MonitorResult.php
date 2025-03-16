<?php

declare(strict_types=1);

namespace Keira\Monitor;

/**
 * Represents a single monitoring result
 */
class MonitorResult
{
    public const STATUS_OK = 'OK';
    public const STATUS_NG = 'NG';

    /**
     * Constructor
     */
    public function __construct(
        private string $id,
        private string $status,
        private int $responseTimeMs,
        private ?int $httpStatus,
        private ?string $error,
        private \DateTimeImmutable $timestamp
    ) {
    }

    /**
     * Create a successful result
     */
    public static function createSuccess(string $id, int $responseTimeMs, int $httpStatus): self
    {
        return new self(
            $id,
            self::STATUS_OK,
            $responseTimeMs,
            $httpStatus,
            null,
            new \DateTimeImmutable()
        );
    }

    /**
     * Create a failed result
     */
    public static function createFailure(string $id, int $responseTimeMs, ?int $httpStatus, string $error): self
    {
        return new self(
            $id,
            self::STATUS_NG,
            $responseTimeMs,
            $httpStatus,
            $error,
            new \DateTimeImmutable()
        );
    }

    /**
     * Get monitor ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get status
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Get response time in milliseconds
     */
    public function getResponseTimeMs(): int
    {
        return $this->responseTimeMs;
    }

    /**
     * Get HTTP status code
     */
    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /**
     * Get error message
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Get timestamp
     */
    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }

    /**
     * Check if result is successful
     */
    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_OK;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'timestamp' => $this->timestamp->format(\DateTimeInterface::ATOM),
            'status' => $this->status,
            'response_time_ms' => $this->responseTimeMs,
            'http_status' => $this->httpStatus,
            'error' => $this->error
        ];
    }
}
