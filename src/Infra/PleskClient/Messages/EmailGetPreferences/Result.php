<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\EmailGetPreferences;

use Laminas\Hydrator\ClassMethodsHydrator as Hydrator;
use Webmozart\Assert\Assert;

class Result
{
    public const STATUS_OK = 'ok';

    public const STATUS_DELETED = 'deleted';

    public const STATUS_ERROR = 'error';

    public string $webmail = '';

    public bool $mailService = false;

    public string $webmailCertificate = '';

    public bool $spamProtectSignEnabled = false;

    protected ?string $status = null;

    protected ?int $errorCode = null;

    protected string $errorMessage = '';

    /** @var array<mixed> */
    protected array $responseBody = [];

    protected string $responseResult = '';

    private bool $catchAllSet = false;

    private string $catchAllForward = '';

    /**
     * @param mixed[] $data
     */
    public static function create(array $data): self
    {
        $data = array_filter($data);

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

    public function setCatchAll(string $catchAll): void
    {
        if (trim($catchAll) !== '') {
            $this->catchAllSet = true;
            $this->catchAllForward = $catchAll;
        }
    }

    public function isCatchAllSet(): bool
    {
        return $this->catchAllSet;
    }

    public function getCatchAllForward(): string
    {
        return $this->catchAllForward;
    }
}
