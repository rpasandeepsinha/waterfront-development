<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteCustomer;

use Waterfront\Support\Traits\HydrateableTrait;

class Parameters
{
    use HydrateableTrait;

    /** @var string */
    private $customerLogin;

    /** @var string|null */
    private $resourceId;

    /**
     * @return string[]
     */
    public static function getRequiredFields(): array
    {
        return [];
    }

    public function setCustomerLogin(string $customerLogin): void
    {
        $this->customerLogin = $customerLogin;
    }

    public function getCustomerLogin(): string
    {
        return $this->customerLogin;
    }

    public function setResourceId(?string $resourceId): void
    {
        $this->resourceId = $resourceId;
    }

    public function getResourceId(): ?string
    {
        return $this->resourceId;
    }
}
