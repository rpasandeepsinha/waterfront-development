<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Legacy;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Repositories\DomainContactRepository;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;
use Webmozart\Assert\Assert;

class NovaUpdateDomainHandlePrivacyProtectJob extends AbstractQueueableJob
{
    public function __construct(
        public readonly string $domainName,
        public readonly bool $dryRun,
    ) {
        parent::__construct();
    }

    public function handle(
        SubscriptionRepository $subscriptionRepository,
        LoggerInterface $logger,
        DomainContactRepository $domainContactRepository,
        DomainService $domainService,
    ): void {
        $baseContext = [
            LoggingContextKeys::DOMAIN_NAME => $this->domainName,
            LoggingContextKeys::ONE_OFF_SCRIPT => NovaBulkUpdateDomainHandlePrivacyProtectAction::SLUG,
            LoggingContextKeys::QUEUE_NAME => $this->queue,
            LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
        ];

        $subscription = $subscriptionRepository->findByDomain($this->domainName);

        if ($subscription === null) {
            $logger->warning(
                'Domain {domain.name} does not exist or is no longer active in Waterfront',
                array_merge($baseContext, [LoggingContextKeys::META => ['dry_run' => $this->dryRun]])
            );

            return;
        }

        $customer = $subscription->customer;
        Assert::notNull($customer->address);

        if ($this->dryRun) {
            $logger->info(
                '[DRY RUN] Would create DomainContact and link RTR handle for domain {domain.name}',
                array_merge($baseContext, [
                    LoggingContextKeys::CUSTOMER_ID => $customer->id,
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                ])
            );

            return;
        }

        $domainContact = $domainContactRepository->createOrFindDomainContact(
            email: $customer->email,
            firstName: $customer->first_name,
            lastName: $customer->last_name,
            phoneCountryCode: $customer->phone_country_code,
            areaCode: $customer->phone_area_code,
            subscriberNumber: $customer->phone_subscriber_number,
            organization: $customer->organization,
            streetName: $customer->address->street_name,
            streetNumber: $customer->address->street_number,
            zipCode: $customer->address->zip_code,
            city: $customer->address->city,
            customerId: $customer->id,
            countryCode: $customer->address->country_code,
            defaultOwner: true,
        );

        $domainDeployment = $subscription->domainDeployment;

        // This can happen is you do a small batch with limit to try out
        if ($domainDeployment !== null && $domainDeployment->contact_owner_id === $domainContact->id) {
            $logger->info(
                'Domain {domain.name} already has the correct contact handle linked, skipping',
                array_merge($baseContext, [
                    LoggingContextKeys::CUSTOMER_ID => $customer->id,
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                ])
            );

            return;
        }

        $domainService->linkContactHandle(
            [['domain' => $this->domainName]],
            $domainContact,
        );

        $logger->info(
            'Created DomainContact and linked RTR handle for domain {domain.name}',
            array_merge($baseContext, [
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
            ])
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::ONE_TIME_SCRIPTS;
    }
}
