<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting\Models\SecretKeyCreate;

use InvalidArgumentException;
use Laminas\Hydrator\ClassMethodsHydrator as Hydrator;

class Result
{
    /**
     * @var string
     */
    public const STATUS_OK = 'ok';

    /**
     * @var string
     */
    public const STATUS_ERROR = 'error';

    /** @var string */
    private $status;

    /** @var int */
    private $errorCode;

    /** @var string */
    private $errorMessage;

    /** @var string */
    private $secretKey;

    public static function create(array $data): Result
    {
        $data = array_filter($data, static fn ($value): bool => $value !== null);

        $hydrator = new Hydrator();

        return $hydrator->hydrate($data, new self());
    }

    public function setStatus(string $status): void
    {
        if (! in_array($status, self::getStatuses(), true)) {
            throw new InvalidArgumentException('The status "' . $status . '" is not valid.');
        }

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

    public function setSecretKey(string $secretKey): void
    {
        $this->secretKey = $secretKey;
    }

    /**
     * @return string
     */
    public function getSecretKey()
    {
        return $this->secretKey;
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        $hydrator = new Hydrator();

        return $hydrator->extract($this);
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
}
