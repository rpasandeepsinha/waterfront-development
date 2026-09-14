<?php

declare(strict_types=1);

namespace Waterfront\Domain\ManualProvisioning\DTO;

use Illuminate\Contracts\Support\Arrayable;
use Ramsey\Uuid\UuidInterface;
use Webmozart\Assert\Assert;

/**
 * @implements Arrayable<string, int|string>
 */
class ProvisionDetails implements Arrayable
{
    public function __construct(
        private readonly int $customerId,
        private readonly int $customerNumber,
        private readonly string $customerFirstName,
        private readonly string $customerLastName,
        private readonly string $customerEmail,
        private readonly UuidInterface $customerUuid,
        private readonly int $subscriptionId,
        private readonly string $productName,
    ) {
        Assert::greaterThan($this->customerId, 0);
        Assert::greaterThan($this->customerNumber, 0);
        Assert::stringNotEmpty($this->customerFirstName);
        Assert::stringNotEmpty($this->customerLastName);
        Assert::email($this->customerEmail);
        Assert::greaterThan($this->subscriptionId, 0);
        Assert::stringNotEmpty($this->productName);
    }

    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    public function getCustomerNumber(): int
    {
        return $this->customerNumber;
    }

    public function getCustomerFirstName(): string
    {
        return $this->customerFirstName;
    }

    public function getCustomerLastName(): string
    {
        return $this->customerLastName;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function getCustomerUuid(): UuidInterface
    {
        return $this->customerUuid;
    }

    public function getSubscriptionId(): int
    {
        return $this->subscriptionId;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    /**
     * @return array<string,int|string|UuidInterface>
     */
    public function toArray(): array
    {
        return [
            'customerId' => $this->customerId,
            'customerNumber' => $this->customerNumber,
            'customerFirstName' => $this->customerFirstName,
            'customerLastName' => $this->customerLastName,
            'customerEmail' => $this->customerEmail,
            'subscriptionId' => $this->subscriptionId,
            'productName' => $this->productName,
        ];
    }
}
