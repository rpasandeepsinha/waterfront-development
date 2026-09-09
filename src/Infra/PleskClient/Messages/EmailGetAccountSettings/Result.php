<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\EmailGetAccountSettings;

use Laminas\Hydrator\ClassMethodsHydrator as Hydrator;
use Waterfront\Infra\PleskClient\DTO\MailAccount;
use Webmozart\Assert\Assert;

class Result
{
    public const STATUS_OK = 'ok';

    public const STATUS_DELETED = 'deleted';

    public const STATUS_ERROR = 'error';

    protected ?string $status = null;

    protected ?int $errorCode = null;

    protected string $errorMessage = '';

    /** @var array<mixed> */
    protected array $responseBody = [];

    protected string $responseResult = '';

    private ?string $catchAllForward = null;

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

    public function addCatchAllForward(string $catchAllForward): void
    {
        $this->catchAllForward = $catchAllForward;
    }

    public function getCatchAllForward(): ?string
    {
        return $this->catchAllForward;
    }

    /**
     * @return array<MailAccount>
     */
    public function getEmailAccounts(): array
    {
        $mailAccountData = [];

        assert(is_array($this->responseBody['mail']));

        if (array_key_exists('mailname', $this->responseBody['mail']['get_info']['result'])) {
            $mailAccountData[] = $this->responseBody['mail']['get_info']['result'];
        } else {
            $mailAccountData = $this->responseBody['mail']['get_info']['result'];
        }

        if (count($mailAccountData) === 1 && array_key_first($mailAccountData) === 'status') {
            return [];
        }

        return array_map(fn (array $mailAccount): MailAccount => new MailAccount(
            mailName: $mailAccount['mailname']['name'],
            mailboxEnabled: $mailAccount['mailname']['mailbox']['enabled'] === 'true',
            mailboxUsage: array_key_exists('usage', $mailAccount['mailname']['mailbox']) ? (int) $mailAccount['mailname']['mailbox']['usage'] : 0,
            forwarding: $mailAccount['mailname']['forwarding']['enabled'] === 'true',
            forwardDestinationAddresses: $this->parseForwardAddresses($mailAccount['mailname']['forwarding'])
        ), $mailAccountData);
    }

    /**
     * @param array<mixed> $forwardAddresses
     *
     * @return array<int, string>|null
     */
    private function parseForwardAddresses(array $forwardAddresses): ?array
    {
        if (array_key_exists('address', $forwardAddresses)) {
            if (is_string($forwardAddresses['address'])) {
                return [$forwardAddresses['address']];
            }
            assert(is_array($forwardAddresses['address']));

            return $forwardAddresses['address'];
        }

        return null;
    }
}
