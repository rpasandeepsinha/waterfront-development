<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\DTO;

class TransferResult
{
    private ?string $reason = null;

    private ?string $exceptionMessage = null;

    private ?string $expirationDate = null;

    private ?string $renewalDate = null;

    private ?string $transferSecret = null;

    public function __construct(private readonly string $status)
    {
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    public function getExpirationDate(): ?string
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(string $expirationDate): self
    {
        $this->expirationDate = $expirationDate;

        return $this;
    }

    public function getRenewalDate(): ?string
    {
        return $this->renewalDate;
    }

    public function setRenewalDate(string $renewalDate): self
    {
        $this->renewalDate = $renewalDate;

        return $this;
    }

    public function getTransferSecret(): ?string
    {
        return $this->transferSecret;
    }

    public function setTransferSecret(string $transferSecret): self
    {
        $this->transferSecret = $transferSecret;

        return $this;
    }

    public function getExceptionMessage(): ?string
    {
        return $this->exceptionMessage;
    }

    public function setExceptionMessage(string $exceptionMessage): TransferResult
    {
        $this->exceptionMessage = $exceptionMessage;
        return $this;
    }
}
