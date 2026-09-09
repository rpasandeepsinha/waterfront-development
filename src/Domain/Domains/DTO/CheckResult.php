<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\DTO;

class CheckResult
{
    /** @var string */
    public const STATUS_FREE = 'free';

    /** @var string */
    public const STATUS_ACTIVE = 'active';

    /** @var string */
    public const STATUS_UNKNOWN = 'unknown';

    /** @var string */
    public const STATUS_INVALID = 'invalid';

    private string $status;

    public function __construct(
        private readonly string $domain,
        string $status,
        private readonly ?string $reason = null,
        private readonly ?bool $isPremium = null,
        private readonly ?int $price = null,
    ) {
        $this->setStatus($status);
    }

    public function setStatus(string $status): void
    {
        if (in_array($status, [self::STATUS_ACTIVE, self::STATUS_FREE, self::STATUS_INVALID], true)) {
            $this->status = $status;
        } else {
            $this->status = self::STATUS_UNKNOWN;
        }
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * @return array<string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_FREE,
            self::STATUS_ACTIVE,
            self::STATUS_UNKNOWN,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'domain' => $this->getDomain(),
            'status' => $this->getStatus(),
            'reason' => $this->getReason(),
        ];
    }

    /** $isPremium can be null because we do not support it for all implementations */
    public function isPremium(): ?bool
    {
        return $this->isPremium;
    }

    /** $price can be null because we do not support it for all implementations */
    public function getPrice(): ?int
    {
        return $this->price;
    }
}
