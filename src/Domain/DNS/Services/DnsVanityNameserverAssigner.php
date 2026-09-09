<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Services;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\DNS\DTO\DnsVanityNameserverConfigDto;
use Waterfront\Domain\DNS\Exceptions\DnsNamerverAlreadyAssignedException;
use Waterfront\Domain\DNS\Exceptions\DnsVanityTldCountMismatchException;
use Waterfront\Domain\DNS\Generators\VanityNameserverGenerator;
use Waterfront\Domain\DNS\Interfaces\NameserverAssignerInterface;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Models\DnsVanityNameserver;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class DnsVanityNameserverAssigner implements NameserverAssignerInterface
{
    public function __construct(
        private readonly VanityNameserverGenerator $vanityNameserverGenerator,
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
        private readonly LoggerInterface $logger,
        private readonly DnsVanityNameserverConfigDto $nameserverConfig,
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws DnsVanityTldCountMismatchException
     */
    public function assign(DnsDeployment $dnsDeployment): array
    {
        Assert::stringNotEmpty($dnsDeployment->subscription->domain);

        if ($dnsDeployment->vanityNameservers()->count() !== 0) {
            throw new DnsNamerverAlreadyAssignedException($dnsDeployment->id, $dnsDeployment->subscription->domain);
        }

        $vanityTlds = $this->nameserverConfig->getNameservers();

        $nameservers = $this->vanityNameserverGenerator->generateVanityNames($dnsDeployment->subscription->domain, $vanityTlds);

        $this->logger->debug('Assigning vanity nameservers to DNS deployment ({domain.name})', [
            LoggingContextKeys::DOMAIN_NAME => $dnsDeployment->subscription->domain,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS->value,
            LoggingContextKeys::SUBSCRIPTION_UUID => $dnsDeployment->subscription_uuid,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::GANDI->value,
            LoggingContextKeys::META => [
                'nameservers' => $nameservers,
            ],
        ]);

        foreach ($nameservers as $nameserver) {
            $dnsDeployment->vanityNameservers()
                ->save(
                    DnsVanityNameserver::firstOrCreate(
                        ['nameserver' => $nameserver]
                    )
                );
        }

        $dnsDeployment->nameserver_type = NameserverType::VANITY;
        $dnsDeployment->save();

        return $this->dnsDeploymentRepository->getNameservers($dnsDeployment);
    }

    public function clear(DnsDeployment $dnsDeployment): void
    {
        if ($dnsDeployment->vanityNameservers()->count() === 0) {
            return;
        }

        $dnsDeployment->vanityNameservers()->detach();
        $dnsDeployment->unsetRelation('vanityNameservers');
    }
}
