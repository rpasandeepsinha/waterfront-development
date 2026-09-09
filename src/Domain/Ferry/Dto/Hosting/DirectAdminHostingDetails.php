<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\Hosting;

use Illuminate\Support\Arr;

readonly class DirectAdminHostingDetails implements HostingDetailsInterface
{
    public function __construct(
        public string $directadminCustomerName,
    ) {
    }

    public function getUsername(): string
    {
        return $this->directadminCustomerName;
    }

    /**
     * @param array<string, string|int> $details
     */
    public static function fromArray(array $details): DirectAdminHostingDetails
    {
        /** @var string $directadminUsername */
        $directadminUsername = Arr::get($details, 'directadmin_customer_name');

        return new self(
            directadminCustomerName: $directadminUsername,
        );
    }

    /**
     * @return array<string, string|int>
     */
    public function toArray(): array
    {
        return [
            'directadmin_customer_name' => $this->directadminCustomerName,
        ];
    }
}
