<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Interfaces\Models\Approver;

use Laminas\Hydrator\ClassMethodsHydrator as Hydrator;

class Result
{
    /** @var string */
    public const STATUS_OK = 'ok';

    /** @var string */
    public const STATUS_ERROR = 'error';

    /** @var string */
    private $status;

    /** @var int */
    private $errorCode;

    /** @var string */
    private $errorMessage;

    /** @var string */
    private $reason;

    /** @var mixed[] */
    private $emails;

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
     * @return int
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    public function setErrorMessage(string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    /**
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
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

    /**
     * @param mixed[] $emails
     */
    public function setEmails(array $emails): void
    {
        $this->emails = $emails;
    }

    /**
     * @return mixed[]
     */
    public function getEmails()
    {
        return $this->emails;
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
     * @return mixed[]
     */
    public function toArray(): array
    {
        $hydrator = new Hydrator();

        return $hydrator->extract($this);
    }
}
