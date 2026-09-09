<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\DTO;

use Waterfront\Domain\Domains\Enums\DomainStatus;

class RegistrationResult
{
    /** @var string */
    private $activationDate;

    /** @var string */
    private $expirationDate;

    /** @var string */
    private $reason;

    private ?string $exceptionMessage = null;

    /**
     * @var string
     */
    private $expOpenprovider;

    /** @var string */
    private $authCode;

    public function __construct(private readonly DomainStatus $status)
    {
    }

    public function getStatus(): DomainStatus
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * @return string
     */
    public function getActivationDate()
    {
        return $this->activationDate;
    }

    /**
     * @return string
     */
    public function getExpirationDate()
    {
        return $this->expirationDate;
    }

    /**
     * @return string
     */
    public function getOpenProviderExpirationDate()
    {
        return $this->expOpenprovider;
    }

    /**
     * @return string
     */
    public function getAuthCode()
    {
        return $this->authCode;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    public function setActivationDate(string $activationDate): void
    {
        $this->activationDate = $activationDate;
    }

    public function setExpirationDate(string $expirationDate): void
    {
        $this->expirationDate = $expirationDate;
    }

    public function setOpenProviderExpirationDate(string $expOpenprovider): void
    {
        $this->expOpenprovider = $expOpenprovider;
    }

    public function setAuthCode(string $authCode): void
    {
        $this->authCode = $authCode;
    }

    public function getExceptionMessage(): ?string
    {
        return $this->exceptionMessage;
    }

    public function setExceptionMessage(string $exceptionMessage): RegistrationResult
    {
        $this->exceptionMessage = $exceptionMessage;
        return $this;
    }
}
