<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DNS\Actions;

use Illuminate\Bus\Dispatcher;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Provision\DNS\Exceptions\DnsDeploymentNotFoundException;
use Waterfront\Domain\Provision\DNS\Exceptions\NameserversNotFoundException;
use Waterfront\Domain\Provision\DNS\Jobs\ValidateDomainNameserverAndUpdateRegistryJob;
use Waterfront\Support\Enums\LoggingContextKeys;

class ValidateDnsPropagationAndRetry
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
        private readonly Dispatcher $busDispatcher,
    ) {
    }

    /**
     * @throws DnsDeploymentNotFoundException
     * @throws NameserversNotFoundException
     */
    public function execute(string $domain): void
    {
        $this->logger->info(
            'Retrying DNS validation on domain [{domain.name}]',
            [LoggingContextKeys::DOMAIN_NAME => $domain],
        );

        $dnsDeployment = $this->dnsDeploymentRepository->getDnsDeploymentFromDomain($domain);

        if ($dnsDeployment === null) {
            throw new DnsDeploymentNotFoundException(sprintf(
                'Tried to retry DNS on domain [%s] without DNS deployment',
                $domain,
            ));
        }

        $this->busDispatcher->dispatch(
            new ValidateDomainNameserverAndUpdateRegistryJob(
                $domain,
                $this->dnsDeploymentRepository->getNameservers($dnsDeployment),
            ),
        );
    }
}
