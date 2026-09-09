<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting\Models\CreateCustomer;

use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result as BaseResult;

class Result extends BaseResult
{
    /** @var string */
    private $customerId;

    /** @var string */
    private $customerGuid;

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerGuid(string $customerGuid): void
    {
        $this->customerGuid = $customerGuid;
    }

    /**
     * @return string
     */
    public function getCustomerGuid()
    {
        return $this->customerGuid;
    }
}
