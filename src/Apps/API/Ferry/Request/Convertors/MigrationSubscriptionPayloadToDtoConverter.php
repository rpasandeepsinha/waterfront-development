<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Request\Convertors;

use Illuminate\Support\Arr;
use Waterfront\Domain\Customers\DTO\CreateSubscriptionsDTO;
use Waterfront\Domain\Ferry\Repositories\MigratedCustomersRepository;

class MigrationSubscriptionPayloadToDtoConverter
{
    public function __construct(
        private readonly MigratedCustomersRepository $migratedCustomersRepository
    ) {
    }

    /**
     * @param array<mixed> $subscriptionPayload
     */
    public function convert(array $subscriptionPayload): CreateSubscriptionsDTO
    {
        $referencedCustomerId = $this->getAsString($subscriptionPayload, 'reference_customer_id');
        $subscriptions = $this->getAsArray($subscriptionPayload, 'subscriptions');

        $customer = $this->migratedCustomersRepository->getFirstCustomerByMigratedCustomerReferenceId($referencedCustomerId);

        return CreateSubscriptionsDTO::create(
            $customer,
            $referencedCustomerId,
            $subscriptions,
        );
    }

    /**
     * @param array<mixed> $payload
     */
    private function getAsString(array $payload, string $key): string
    {
        /** @var string|null $value */
        $value = Arr::get($payload, $key, '');

        // Can still be null if the key exists
        if ($value === null) {
            return '';
        }

        return $value;
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array<mixed>
     */
    private function getAsArray(array $payload, string $key): array
    {
        $value = Arr::get($payload, $key, []);

        if (! is_array($value)) {
            return [];
        }

        return $value;
    }
}
