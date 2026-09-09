<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\Hosting;

use Deprecated;
use Illuminate\Support\Arr;

readonly class PleskHostingDetails implements HostingDetailsInterface
{
    public function __construct(
        public string $pleskCustomerUsername,
        public int|null $pleskCustomerId = null,
    ) {
    }

    public function getUsername(): string
    {
        return $this->pleskCustomerUsername;
    }

    #[Deprecated(message: 'In the case of plesk we should always use usernames since ID can be the same over multiple servers')]
    public function getId(): int|null
    {
        return $this->pleskCustomerId;
    }

    /**
     * @param array<string, string|int> $details
     */
    public static function fromArray(array $details): PleskHostingDetails
    {
        /** @var string $pleskCustomerUsername */
        $pleskCustomerUsername = Arr::get($details, 'plesk_customer_username');
        /** @var int|null $pleskCustomerId */
        $pleskCustomerId = Arr::get($details, 'plesk_customer_id');

        return new self(
            pleskCustomerUsername: $pleskCustomerUsername,
            pleskCustomerId: $pleskCustomerId,
        );
    }

    /**
     * @return array{plesk_customer_username: string, plesk_customer_id: int|null}
     */
    public function toArray(): array
    {
        return [
            'plesk_customer_username' => $this->pleskCustomerUsername,
            'plesk_customer_id' => $this->pleskCustomerId,
        ];
    }
}
