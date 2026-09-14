<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Interfaces\Models;

use Laminas\Hydrator\ClassMethodsHydrator as Hydrator;

class Result
{
    /** @var string */
    public const STATUS_OK = 'ok';

    /** @var string */
    public const STATUS_DELETED = 'deleted';

    /** @var string */
    public const STATUS_ERROR = 'error';

    /** @var string */
    public const STATUS_ISSUED = 'Issued';

    /** @var string */
    public const STATUS_WAITING = 'waiting';

    /** @var string */
    private $status;

    /** @var ?int */
    private $errorCode;

    /** @var ?string */
    private $errorMessage;

    /** @var string */
    private $reason;

    /** @var int */
    private $certificateId;

    /** @var string */
    private $certificateStatus;

    private bool $isCertificateActive;

    private ?string $dnsRecord = null;

    private ?string $dnsValue = null;

    /** @var mixed[] */
    private ?array $responseData = null;

    /** @var int */
    private $requestId;

    private ?string $csr = null;

    public static function create(array $data): Result
    {
        $data = array_filter($data, fn (mixed $value): bool => (bool) $value);

        $hydrator = new Hydrator();

        return $hydrator->hydrate($data, new self());
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    public function setErrorCode(int $errorCode): void
    {
        $this->errorCode = $errorCode;
    }

    /**
     * @return ?int
     */
    public function getErrorCode()
    {
        return $this->errorCode ?? null;
    }

    public function setErrorMessage(string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    /**
     * @return ?string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage ?? null;
    }

    public function setReason(string $reason): void
    {
        $this->reason = $reason;
    }

    /**
     * @return string
     */
    public function getReason()
    {
        return $this->reason;
    }

    public function setCertificateId(int $certificateId): void
    {
        $this->certificateId = $certificateId;
    }

    /**
     * @return int
     */
    public function getCertificateId()
    {
        return $this->certificateId;
    }

    public function setCertificateStatus(string $certificateStatus): void
    {
        $this->certificateStatus = $certificateStatus;
    }

    /**
     * @return string
     */
    public function getCertificateStatus()
    {
        return $this->certificateStatus;
    }

    public function setIsCertificateActive(bool $isCertificateActive): void
    {
        $this->isCertificateActive = $isCertificateActive;
    }

    public function isCertificateActive(): bool
    {
        return $this->isCertificateActive ?? false;
    }

    public function setDnsRecord(string $dnsRecord): void
    {
        $this->dnsRecord = $dnsRecord;
    }

    /**
     * @example Ex. _72a713574b838ff44fd689915b2a8408.storetesterdetest.nl
     */
    public function getDnsRecord(): string
    {
        return $this->dnsRecord ?? '';
    }

    public function setDnsValue(string $dnsValue): void
    {
        $this->dnsValue = $dnsValue;
    }

    public function getDnsValue(): ?string
    {
        return $this->dnsValue;
    }

    /**
     * @return string[]
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_OK,
            self::STATUS_ERROR,
        ];
    }

    /**
     * @param mixed[] $data
     */
    public function setResponseData(array $data): void
    {
        $this->responseData = $data;
    }

    /**
     * @return mixed[]|null
     */
    public function getResponseData(): ?array
    {
        return $this->responseData;
    }

    public function setRequestId(int $requestId): void
    {
        $this->requestId = $requestId;
    }

    public function getRequestId(): ?int
    {
        return $this->requestId;
    }

    public function getCsr(): ?string
    {
        return $this->csr;
    }

    public function setCsr(string $csr): void
    {
        $this->csr = $csr;
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        $hydrator = new Hydrator();

        return $hydrator->extract($this);
    }
}
