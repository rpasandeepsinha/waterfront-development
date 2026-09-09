<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Actions;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Builder;
use JsonException;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Exceptions\DnsDeploymentNotFoundException;
use Waterfront\Domain\DNS\Exceptions\DnsNamerverAlreadyAssignedException;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Exceptions\FailedToFetchNameserversException;
use Waterfront\Domain\DNS\Factories\NameserverAssignerFactory;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\DNS\Services\PowerDnsNameserverSynchronizer;
use Waterfront\Domain\Domains\Exceptions\DomainModificationFailedException;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class AssignNameserversToDomainAction
{
    public function __construct(
        private readonly DomainServiceFactory $domainServiceFactory,
        private readonly PowerDnsNameserverSynchronizer $powerDnsNameserverSynchronizer,
        private readonly DnsService $dnsService,
        private readonly DnsProductSpecRepository $dnsProductSpecRepository,
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
        private readonly NameserverAssignerFactory $nameserverAssignerFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     * @throws PdnsResponseException
     * @throws DnsZoneNotFoundException
     * @throws DomainModificationFailedException
     * @throws FailedToFetchNameserversException
     * @throws DnsNamerverAlreadyAssignedException
     * @throws DnsDeploymentNotFoundException
     */
    public function assign(DomainDeployment $domainDeployment, bool $shouldProvision = true): void
    {
        $domain = $domainDeployment->subscription->domain;
        Assert::stringNotEmpty($domain, 'Provided subscription has no domain');

        $dnsDeployment = $this->dnsDeploymentRepository->getDnsDeploymentFromDomain($domain);

        if ($dnsDeployment === null) {
            throw new DnsDeploymentNotFoundException($domain);
        }

        $assigner = $this->nameserverAssignerFactory->createAssigner($dnsDeployment->nameserver_type);

        $nameservers = $this->dnsDeploymentRepository->isNameserversAlreadyAssigned($dnsDeployment)
            ? $this->dnsDeploymentRepository->getNameservers($dnsDeployment)
            : $assigner->assign($dnsDeployment);

        $nameservers = $this->deduplicateNameservers($nameservers);

        if ($shouldProvision) {
            $this->provisionDns($dnsDeployment->subscription, $domain, $nameservers);
            $this->provisionRegistry($domainDeployment, $domain, $nameservers);
        }
    }

    /**
     *
     * @throws DnsZoneNotFoundException
     * @throws DomainModificationFailedException
     * @throws JsonException
     */
    public function assignToDomain(string $domain): void
    {
        /** @var DomainDeployment $domainDeployment */
        $domainDeployment = DomainDeployment::query()
            ->whereHas(
                'subscription',
                static function (Builder $builder) use ($domain): void {
                    $builder->where('domain', '=', $domain);
                }
            )
            ->with(['provider', 'subscription'])
            ->firstOrFail();

        $this->assign($domainDeployment);
    }

    /**
     * @param Nameserver[] $nameservers
     *
     * @throws DnsZoneNotFoundException
     * @throws JsonException
     * @throws GuzzleException
     * @throws PdnsResponseException
     */
    private function provisionDns(
        Subscription $dnsSubscription,
        string $domain,
        array $nameservers
    ): void {
        $isPremiumDns = $this->dnsProductSpecRepository->isPremiumDns($dnsSubscription->product);

        $this->logger->info(
            sprintf(
                'Provision DNS for domain %s',
                $domain,
            ),
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => [
                    'nameservers' => $nameservers,
                    'is_premium_dns' => $isPremiumDns,
                ],
            ]
        );

        if ($isPremiumDns) {
            $dnsSubscription->technical_status = TechnicalStatus::PENDING->value;
            $dnsSubscription->save();

            $this->dnsService->enablePremiumDns($domain);
            return;
        }

        $this->powerDnsNameserverSynchronizer->synchronize($domain, $nameservers, $isPremiumDns);
    }

    /**
     * @param Nameserver[] $nameservers
     *
     * @throws DomainModificationFailedException
     */
    private function provisionRegistry(
        DomainDeployment $domainDeployment,
        string $domain,
        array $nameservers
    ): void {
        $this->logger->info(
            sprintf(
                'Updating nameservers for domain %s',
                $domain,
            ),
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::PROVISIONING_PROVIDER => $domainDeployment->provider->slug,
                LoggingContextKeys::META => [
                    'nameservers' => $nameservers,
                ],
            ]
        );

        $this->domainServiceFactory->driver(
            $domainDeployment->provider->slug,
            $domainDeployment->businessUnit
        )->updateNameServers(
            $domain,
            $nameservers
        );
    }

    /**
     * @param Nameserver[] $nameservers
     *
     * @return Nameserver[]
     */
    private function deduplicateNameservers(array $nameservers): array
    {
        $uniqueNameservers = [];
        foreach ($nameservers as $ns) {
            $key = strtolower($ns->hostname);
            $uniqueNameservers[$key] = $ns;
        }
        return array_values($uniqueNameservers);
    }
}
