<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Models;

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

    /**
     * @var mixed[]
     */
    private $paymentData;

    public static function create(array $data): Result
    {
        $data = array_filter($data, fn (mixed $value): bool => (bool) $value);

        $hydrator = new Hydrator();

        return $hydrator->hydrate($data, new self());
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function setErrorCode(int $errorCode): void
    {
        $this->errorCode = $errorCode;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    /**
     * @return mixed[]
     */
    public function getPaymentData(): array
    {
        return $this->paymentData;
    }

    /**
     * @param mixed[] $paymentData
     */
    public function setPaymentData(array $paymentData): void
    {
        $this->paymentData = $paymentData;
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
