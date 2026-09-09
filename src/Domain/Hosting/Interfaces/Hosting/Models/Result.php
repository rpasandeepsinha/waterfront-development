<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting\Models;

use Laminas\Hydrator\ClassMethodsHydrator as Hydrator;
use Webmozart\Assert\Assert;

class Result
{
    public const STATUS_OK = 'ok';

    public const STATUS_DELETED = 'deleted';

    public const STATUS_ERROR = 'error';

    protected ?string $status = null;

    protected ?int $errorCode = null;

    protected string $errorMessage = '';

    protected ?string $resourceId = null;

    protected ?int $serverId = null;

    /** @var array<mixed> */
    protected array $responseBody = [];

    protected string $responseResult = '';

    /**
     * @param mixed[] $data
     */
    public static function create(array $data): Result
    {
        $data = array_filter($data, static fn ($value): bool => $value !== null);

        return new Hydrator()->hydrate($data, new self());
    }

    public function setResourceId(?string $resourceId): void
    {
        $this->resourceId = $resourceId;
    }

    public function getResourceId(): ?string
    {
        return $this->resourceId;
    }

    public function setServerId(?int $serverId): void
    {
        $this->serverId = $serverId;
    }

    public function getServerId(): ?int
    {
        return $this->serverId;
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

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        return new Hydrator()->extract($this);
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
}
