<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Listeners;

use Illuminate\Bus\Dispatcher;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Exceptions\FetchDomainException;
use Waterfront\Domain\Domains\Jobs\RegisterDomainNameJob;
use Waterfront\Domain\Domains\Jobs\UpdateDomainNameRegistrationJob;
use Waterfront\Domain\Provision\DNS\Events\DnsProvisioned;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\RtrClient\Services\Enums\DomainStatus as RtrDomainStatus;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

readonly class DnsProvisionedListener
{
    public function __construct(
        private DnsDeploymentRepository $dnsDeploymentRepository,
        private LoggerInterface $logger,
        private Dispatcher $dispatcher,
        private DomainService $domainService,
    ) {
    }

    public function handle(DnsProvisioned $dnsEvent): void
    {
        $dnsDeployment = $dnsEvent->dnsDeployment;

        $this->logger->info(
            'DNS deployment provisioned for [{subscription.uuid}]',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $dnsDeployment->subscription_uuid,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
            ],
        );

        $domainDeployment = $this->dnsDeploymentRepository->getDomainDeployment($dnsDeployment);

        if ($domainDeployment === null) {
            $this->logger->warning(
                'DnsProvisioned without domain parent subscription or product',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $dnsDeployment->subscription_uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                ],
            );

            return;
        }

        /*
         * Do not try to register or update the domain at the register if we know it has failed already.
         * This could for example happen when an invalid transfer token was given during the order.
         * This also prevents us from getting the register from being spammed with requests.
         */
        $domainSubscription = $domainDeployment->subscription;
        if (
            $domainSubscription->technical_status === TechnicalStatus::FAILED->value
            || $domainSubscription->technical_status === TechnicalStatus::TRANSFER_FAILED->value
        ) {
            $this->logger->info(
                'Domain registration failed, not trying to register or update domain',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $domainSubscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                ],
            );

            return;
        }

        $domain = $domainDeployment->subscription->domain;
        Assert::notNull($domain);

        $nameservers = $this->dnsDeploymentRepository->getNameservers($dnsDeployment);

        $domainRegistered = true;

        try {
            $this->domainService->fetchDomain($domain, $domainDeployment->provider->slug);
        } catch (FetchDomainException) {
            $domainRegistered = false;
        }

        if ($domainRegistered) {
            $this->logger->info(
                sprintf('Domain already registered, will update domain %s', $domain),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $domainDeployment->subscription_uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                ],
            );
            $this->dispatcher->dispatch(new UpdateDomainNameRegistrationJob($domainDeployment, $nameservers));

            return;
        }

        if (
            $domainSubscription->technical_status === TechnicalStatus::PENDING->value
            && $domainDeployment->domain_status === RtrDomainStatus::PENDING_VALIDATION
        ) {
            $this->logger->info(
                'Domain TLD requires customer validation. Stopping domain registration until validation is completed.',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $domainSubscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $domainSubscription->domain,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                ],
            );

            return;
        }

        $registrationRequiresDnsBeforeSubmission =
            $this->domainService->registrationRequiresDnsBeforeSubmission($domain);

        /**
         * If the domain subscription is in PENDING status and there is no zone check or nameserver requirement,
         * it means a transfer has already been submitted to the registrar. In that scenario we cannot fetch
         * the domain yet because the losing registrar has not released it. If we were to dispatch
         * RegisterDomainNameJob anyway, it would initiate a second transfer request at the registrar which
         * will result in the subscription technical_status being set at failed.
         *
         * However, when a zone check or nameserver requirement is in place,
         * the DomainCreationListener intentionally sets the subscription to PENDING and then returns early
         * without submitting anything to the registrar — it defers the actual registration/transfer to this
         * listener, which runs after the DNS zone has been successfully created. In that case the subscription
         * is PENDING but no transfer has been submitted yet, so we must NOT skip: we need to fall through and
         * dispatch RegisterDomainNameJob to perform the transfer (or registration) for the first time.
         */
        if (
            $domainDeployment->subscription->technical_status === TechnicalStatus::PENDING->value
            && ! $registrationRequiresDnsBeforeSubmission
        ) {
            $this->logger->info(
                'Domain {domain.name} is currently transferring will not attempt to register domain',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $domainDeployment->subscription_uuid,
                    LoggingContextKeys::DOMAIN_NAME => $domainDeployment->subscription->domain,
                    LoggingContextKeys::META => [
                        'last_result' => $domainDeployment->last_result,
                        'last_result_received' => $domainDeployment->last_result_received,
                    ],
                ],
            );

            return;
        }

        $this->logger->info(
            sprintf('Domain not registered, will register with domain nameservers %s', $domain),
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $domainDeployment->subscription_uuid,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
            ],
        );

        $this->dispatcher->dispatch(new RegisterDomainNameJob($domainDeployment));
    }
}
