<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DNS\Jobs;

use Illuminate\Bus\Dispatcher;
use Illuminate\Container\Container;
use Psr\Log\LoggerInterface;
use PurplePixie\PhpDns\DNSResult;
use PurplePixie\PhpDns\DNSTypes;
use PurplePixie\PhpDns\Exceptions\InvalidQueryTypeName;
use Throwable;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\Domains\Jobs\UpdateDomainNameRegistrationJob;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Ferry\Exceptions\DomainDeploymentNotFoundException;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Helpers\DnsHelper;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class ValidateDomainNameserverAndUpdateRegistryJob extends AbstractQueueableJob
{
    public int $tries = 7;

    /**
     * @param Nameserver[] $nameservers
     */
    public function __construct(
        private readonly string $domain,
        private readonly array $nameservers
    ) {
        parent::__construct();
    }

    public function failed(?Throwable $throwable): void
    {
        $container = Container::getInstance();
        $logger = $container->make(LoggerInterface::class);
        $logger->error(
            'Error trying to validate DNS for domain {domain.name} job definitely failed after {job.attempt} attempts',
            [
                LoggingContextKeys::DOMAIN_NAME => $this->domain,
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::EXCEPTION => $throwable,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS->value,
                LoggingContextKeys::META => [
                    'nameservers' => $this->nameservers,
                ],
            ]
        );

        /** @var SubscriptionRepository $subscriptionRepository */
        $subscriptionRepository = $container->make(SubscriptionRepository::class);
        $subscription = $subscriptionRepository
            ->getSubscriptionByDomainAndGroup($this->domain, ProductGroupType::EXTENSION);

        $subscription->update([
            'technical_status' => TechnicalStatus::FAILED->value,
        ]);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return int[]
     */
    public function backoff(): array
    {
        return [
            5,
            10,
            15,
            30,
            60,
            120,
            300,
        ];
    }

    /**
     * @throws InvalidQueryTypeName
     */
    public function handle(
        LoggerInterface $logger,
        DnsHelper $dnsHelper,
        Dispatcher $busDispatcher,
        DomainDeploymentRepository $domainDeploymentRepository,
    ): void {
        foreach ($this->nameservers as $ns) {
            $query = $dnsHelper->createDnsQuery($ns->hostname);

            $dnsAnswer = $query->query($this->domain, DNSTypes::NAME_NS);

            if ($dnsAnswer === false || ! $dnsAnswer->valid()) {
                $logger->debug(
                    sprintf('Could not resolve DNS for domain {domain.name} with nameserver %s', $ns->hostname),
                    [
                        LoggingContextKeys::DOMAIN_NAME => $this->domain,
                    ]
                );
                $this->release($this->getBackoffDelay());
                return;
            }

            $nameserversResponse = array_map(
                fn (DNSResult $dnsResult) => $dnsResult->getData(),
                [...$dnsAnswer]
            );

            if (! in_array($ns->hostname, $nameserversResponse, true)) {
                $logger->warning(
                    sprintf('Resolved DNS for domain {domain.name} but nameserver %s is missing', $ns->hostname),
                    [
                        LoggingContextKeys::DOMAIN_NAME => $this->domain,
                        LoggingContextKeys::META        => [
                            'nameservers_from_dns' => $nameserversResponse,
                        ],
                    ]
                );

                $this->release($this->getBackoffDelay());
                return;
            }

            $logger->debug(sprintf('Resolved domain {domain.name} with nameserver %s', $ns->hostname), [
                LoggingContextKeys::DOMAIN_NAME => $this->domain,
            ]);
        }

        $logger->debug('All nameservers resolved for domain {domain.name}, updating registry', [
            LoggingContextKeys::DOMAIN_NAME => $this->domain,
        ]);

        $domainDeployment = $domainDeploymentRepository->getActiveDeploymentByDomain($this->domain);

        if ($domainDeployment === null) {
            $this->fail(
                new DomainDeploymentNotFoundException(
                    sprintf(
                        'Could not find domain deployment for domain %s',
                        $this->domain
                    )
                )
            );
            return;
        }

        $busDispatcher->dispatch(new UpdateDomainNameRegistrationJob($domainDeployment, $this->nameservers));
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::DNS;
    }
}
