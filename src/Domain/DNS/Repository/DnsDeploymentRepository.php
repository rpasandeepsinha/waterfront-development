<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Repository;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Exceptions\FailedToFetchNameserversException;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Webmozart\Assert\Assert;

class DnsDeploymentRepository
{
    public function create(string $subscriptionUuid, NameserverType $nameserverType): DnsDeployment
    {
        return DnsDeployment::updateOrcreate(
            [
                'subscription_uuid' => $subscriptionUuid,
            ],
            [
                'nameserver_type' => $nameserverType,
            ],
        );
    }

    public function saveLastPremiumProviderResponse(DnsDeployment $dnsDeployment, string $response): bool
    {
        $dnsDeployment->last_result_premium_provider_received = CarbonImmutable::now();
        $dnsDeployment->last_result_premium_provider = $response;

        return $dnsDeployment->save();
    }

    public function saveLastResponse(DnsDeployment $dnsDeployment, string $response): bool
    {
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->last_result = $response;

        return $dnsDeployment->save();
    }

    public function setNameserverType(DnsDeployment $dnsDeployment, NameserverType $type): bool
    {
        $dnsDeployment->nameserver_type = $type;

        return $dnsDeployment->save();
    }

    public function isNameserversAlreadyAssigned(DnsDeployment $dnsDeployment): bool
    {
        $storedNameservers = $this->getStoredNameservers($dnsDeployment);

        return $storedNameservers->isNotEmpty();
    }

    /**
     * @throws FailedToFetchNameserversException
     *
     * @return Nameserver[]
     */
    public function getNameservers(DnsDeployment $dnsDeployment): array
    {
        $storedNameservers = $this->getStoredNameservers($dnsDeployment);

        if ($storedNameservers->isEmpty()) {
            $domain = $dnsDeployment->subscription->domain;
            Assert::notNull($domain);
            throw new FailedToFetchNameserversException($domain);
        }

        $nameservers = [];
        foreach ($storedNameservers as $nameserver) {
            /** @var string $nameserverHostname */
            $nameserverHostname = $nameserver;
            $nameservers[] = new Nameserver($nameserverHostname);
        }

        return $nameservers;
    }

    public function getDomainDeployment(DnsDeployment $dnsDeployment): ?DomainDeployment
    {
        return $dnsDeployment
            ->subscription
            ->parent()
            ->whereHas('product.productGroup', function (Builder $builder) {
                $builder->where('slug', ProductGroupType::EXTENSION);
            })
            ->first()
            ?->domainDeployment;
    }

    public function getDnsDeploymentFromDomainDeployment(DomainDeployment $deployment): ?DnsDeployment
    {
        $domain = $deployment->subscription->domain;

        return $domain === null ? null : $this->getDnsDeploymentFromDomain($domain);
    }

    public function getDnsDeploymentFromDomain(string $domain): ?DnsDeployment
    {
        return DnsDeployment::whereHas('subscription', function (Builder $builder) use ($domain) {
            $builder->whereHas('parent', function (Builder $builder) use ($domain) {
                $builder->whereHas('product.productGroup', function (Builder $builder) {
                    $builder->where('slug', ProductGroupType::EXTENSION);
                })->where('domain', $domain);
            });
        })->first();
    }

    /**
     * @return string[]
     */
    public function getNameserverHostnamesFromDomainDeployment(DomainDeployment $deployment): array
    {
        $dnsDeployment = $this->getDnsDeploymentFromDomainDeployment($deployment);

        if ($dnsDeployment === null) {
            return [];
        }

        /** @var string[] $nameserverHostnames */
        $nameserverHostnames = $this->getStoredNameservers($dnsDeployment)->toArray();

        return $nameserverHostnames;
    }

    /**
     * @return string[]
     */
    public function getNameserverHostnames(DnsDeployment $dnsDeployment): array
    {
        /** @var string[] $nameserverHostnames */
        $nameserverHostnames = $this->getStoredNameservers($dnsDeployment)->toArray();

        return $nameserverHostnames;
    }

    public function findById(int $id): ?DnsDeployment
    {
        /** @var ?DnsDeployment $dnsDeployment */
        $dnsDeployment = DnsDeployment::find($id);

        return $dnsDeployment;
    }

    /**
     * @return Collection<int|string, mixed>
     */
    private function getStoredNameservers(DnsDeployment $dnsDeployment): Collection
    {
        return match ($dnsDeployment->nameserver_type) {
            NameserverType::INTERNAL => $dnsDeployment->dnsNameservers()->get()->pluck('nameserver'),
            NameserverType::VANITY => $dnsDeployment->vanityNameservers()->get()->pluck('nameserver'),
            NameserverType::EXTERNAL => $dnsDeployment->externalNameservers()->get()->pluck('nameserver'),
        };
    }
}
