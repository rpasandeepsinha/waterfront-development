<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\Hosting;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Subscriptions\Models\Subscription;

readonly class HostingMigrationPayload
{
    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    public function __construct(
        public Collection $subscriptions,
        public string $referenceSubscriptionId,
        public string $driver,
        public string $serverName,
        public HostingDetailsInterface $hostingDetails,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): HostingMigrationPayload
    {
        /** @var Collection<int, Subscription> $subscriptions */
        $subscriptions = Arr::get($payload, 'subscriptions');
        /** @var string $referenceSubscriptionId */
        $referenceSubscriptionId = Arr::get($payload, 'referenceSubscriptionId');
        /** @var string $driver */
        $driver = Arr::get($payload, 'driver');
        /** @var string $serverName */
        $serverName = Arr::get($payload, 'server_name');
        /** @var array<string, string|int> $hostingDetails */
        $hostingDetails = Arr::get($payload, 'server_data', []);

        $hostingDetailsDTO = self::resolveTechnicalDetails($hostingDetails, $driver);

        return new self(
            subscriptions: $subscriptions,
            referenceSubscriptionId: $referenceSubscriptionId,
            driver: $driver,
            serverName: $serverName,
            hostingDetails: $hostingDetailsDTO,
        );
    }

    /**
     * @return array<string, string|Collection<int, Subscription>|array<string, string|int|null>>
     */
    public function toArray(): array
    {
        return [
            'referenceSubscriptionId' => $this->referenceSubscriptionId,
            'driver' => $this->driver,
            'server_name' => $this->serverName,
            'hostingDetails' => $this->hostingDetails->toArray(),
        ];
    }

    /**
     * @param array<string, string|int> $details
     */
    public static function resolveTechnicalDetails(array $details, string $driver): HostingDetailsInterface
    {
        return match ($driver) {
            ProviderSlug::DIRECTADMIN->value => DirectAdminHostingDetails::fromArray($details),
            ProviderSlug::PLESK->value => PleskHostingDetails::fromArray($details),
            ProviderSlug::BASEKIT->value => SitebuilderBaseKitDetails::fromArray($details),
            default => throw new InvalidArgumentException(sprintf('Unexpected match value provided: %s', $driver)),
        };
    }
}
