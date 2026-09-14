<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;

/**
 * @extends Factory<DnsDeployment>
 */
class DnsDeploymentFactory extends Factory
{
    protected $model = DnsDeployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'last_result_received' => CarbonImmutable::now(),
            'last_result' => json_encode([]),
            'last_result_premium_provider_received' => CarbonImmutable::now(),
            'last_result_premium_provider' => json_encode([]),
            'nameserver_type' => NameserverType::INTERNAL,
        ];
    }

    public function withExternalNameserver(): DnsDeploymentFactory
    {
        return $this->has(
            new DnsExternalNameserverFactory(),
            'externalNameservers',
        )->state(fn () => [
            'nameserver_type' => NameserverType::EXTERNAL,
        ]);
    }

    public function withInternalNameserver(): DnsDeploymentFactory
    {
        return $this->has(
            new DnsNameserverFactory()->for(new DnsRegionFactory()),
            'dnsNameservers',
        )->state(fn () => [
            'nameserver_type' => NameserverType::INTERNAL,
        ]);
    }

    public function withVanityNameserver(): DnsDeploymentFactory
    {
        return $this->has(
            new DnsVanityNameserverFactory(),
            'vanityNameservers',
        )->state(fn () => [
            'nameserver_type' => NameserverType::VANITY,
        ]);
    }

    public function premiumDns(): DnsDeploymentFactory
    {
        return $this->withVanityNameserver();
    }
}
