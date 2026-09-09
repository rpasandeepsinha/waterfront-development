<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\CreateSite;

use Laminas\Hydrator\ClassMethodsHydrator as Hydrator;
use Webmozart\Assert\Assert;

class CreateSiteResult
{
    public const STATUS_OK = 'ok';

    public const STATUS_ERROR = 'error';

    public string $domainId;

    public string $domainGuid;

    protected ?string $status = null;

    protected ?int $errorCode = null;

    protected string $errorMessage = '';

    /** @var array<mixed> */
    protected array $responseBody = [];

    protected string $responseResult = '';

    /**
     * @param mixed[] $data
     */
    public static function create(array $data): self
    {
        return new Hydrator()->hydrate($data, new self());
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return new Hydrator()->extract($this);
    }

    /**
     * @return array<string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_OK,
            self::STATUS_ERROR,
        ];
    }

    /**
     * @param array<mixed> $responseBody
     */
    public function setResponseBody(array $responseBody): void
    {
        $this->responseBody = $responseBody;
    }

    /**
     * @return array<mixed>
     */
    public function getResponseBody(): array
    {
        return $this->responseBody;
    }

    public function setResponseResult(string $responseResult): void
    {
        $this->responseResult = $responseResult;
    }

    public function getResponseResult(): string
    {
        return $this->responseResult;
    }

    public function setStatus(string $status): void
    {
        Assert::oneOf($status, self::getStatuses());

        $this->status = $status;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setErrorCode(int $errorCode): void
    {
        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): ?int
    {
        return $this->errorCode;
    }

    public function setErrorMessage(string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setDomainId(string $domainId): void
    {
        $this->domainId = $domainId;
    }

    public function getDomainId(): string
    {
        return $this->domainId;
    }

    public function setDomainGuid(string $domainGuid): void
    {
        $this->domainGuid = $domainGuid;
    }

    public function getDomainGuid(): string
    {
        return $this->domainGuid;
    }
}
