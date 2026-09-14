<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\DnsGetRecords;

use Laminas\Hydrator\ClassMethodsHydrator as Hydrator;
use Waterfront\Infra\PleskClient\DTO\DnsRecord;
use Webmozart\Assert\Assert;

class DnsRecordsResult
{
    public const STATUS_OK = 'ok';

    public const STATUS_DELETED = 'deleted';

    public const STATUS_ERROR = 'error';

    /**
     * @var DnsRecord[]
     */
    public array $records;

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
        $data = array_filter($data, fn (mixed $value): bool => (bool) $value);

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
}
