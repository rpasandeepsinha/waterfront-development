<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Job;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Dispatcher;
use Illuminate\Container\Container;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\DNS\Exceptions\FailedToFetchNameserversException;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Domains\Jobs\UpdateDomainNameRegistrationJob;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;
use Webmozart\Assert\Assert;

class SetNameserversForDomainJob extends AbstractQueueableJob
{
    public function __construct(
        public string $domain,
    ) {
        parent::__construct();
    }

    public function getQueueName(): QueueName
    {
        return QueueName::PARTNER_DOMAIN;
    }

    public function failed(Throwable $exception): void
    {
        $container = Container::getInstance();

        $logger = $container->make(LoggerInterface::class);
        $domainDeploymentRepository = $container->make(DomainDeploymentRepository::class);

        $domainDeployment = $domainDeploymentRepository->getActiveDeploymentByDomain($this->domain);

        if ($domainDeployment === null) {
            $logger->error(
                'Trying to set domain subscription to FAILED but unable to find active domain subscription for domain {domain.name}',
                [
                    LoggingContextKeys::DOMAIN_NAME => $this->domain,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return;
        }

        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->last_result = json_encode([
            'message' => 'Failed to start nameserver update for domain',
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTrace(),
        ], JSON_THROW_ON_ERROR);
        $domainDeployment->save();

        $subscription = $domainDeployment->subscription;
        $subscription->technical_status = TechnicalStatus::FAILED->value;
        $subscription->save();
    }

    /**
     * @throws FailedToFetchNameserversException
     */
    public function handle(
        DnsDeploymentRepository $dnsDeploymentRepository,
        Dispatcher $busDispatcher,
    ): void {
        $dnsDeployment = $dnsDeploymentRepository->getDnsDeploymentFromDomain($this->domain);
        Assert::notNull($dnsDeployment, sprintf('Expected to have DnsDeployment for domain %s', $this->domain));

        $domainDeployment = $dnsDeploymentRepository->getDomainDeployment($dnsDeployment);
        Assert::notNull($domainDeployment, sprintf(
            'Expected to have DomainDeployment with DNS for domain %s',
            $this->domain,
        ));

        $nameservers = $dnsDeploymentRepository->getNameservers($dnsDeployment);
        $busDispatcher->dispatch(new UpdateDomainNameRegistrationJob($domainDeployment, $nameservers));
    }
}
