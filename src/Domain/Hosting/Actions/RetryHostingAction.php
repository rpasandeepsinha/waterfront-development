<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Actions;

use Illuminate\Contracts\Bus\Dispatcher as JobDispatcher;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Hosting\Enums\HostingRetryType;
use Waterfront\Domain\Hosting\Events\CreateHosting;
use Waterfront\Domain\MailManagement\Events\CreateMailOnlyHosting;
use Waterfront\Domain\ResellerHosting\Jobs\CreateResellerHostingJob;
use Waterfront\Domain\Sitebuilder\Events\CreateSitebuilder;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class RetryHostingAction
{
    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
        private readonly JobDispatcher $jobDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(Subscription $subscription, HostingRetryType $type, ?int $serverId): void
    {
        $subscription->loadMissing(['customer', 'product', 'hostingDeployment.provider']);

        match ($type) {
            HostingRetryType::BASIC => $this->retryBasicHosting($subscription, $serverId),
            HostingRetryType::SITEBUILDER => $this->retrySitebuilderHosting($subscription),
            HostingRetryType::MAIL_ONLY => $this->retryMailOnlyHosting($subscription),
            HostingRetryType::RESELLER_HOSTING => $this->retryResellerHosting($subscription, $serverId),
        };
    }

    private function retryBasicHosting(Subscription $subscription, ?int $serverId): void
    {
        if ($subscription->technical_status === TechnicalStatus::ERROR->value) {
            $this->logger->error(
                'Deleting hosting deployment',
                [
                    LoggingContextKeys::SERVER_ID => $subscription->hostingDeployment?->server_id,
                    LoggingContextKeys::PROVISIONING_PROVIDER =>
                        $subscription->hostingDeployment?->provider?->slug->value,
                    LoggingContextKeys::META => [
                        'sitebuilder_provider_id' => $subscription->hostingDeployment?->sitebuilder_provider_id,
                        'mail_only_provider_id' => $subscription->hostingDeployment?->mail_only_provider_id,
                        'basekit_user_ref' => $subscription->hostingDeployment?->basekit_user_ref,
                        'basekit_site_ref' => $subscription->hostingDeployment?->basekit_site_ref,
                        'basekit_server_id' => $subscription->hostingDeployment?->basekit_server_id,
                        'directadmin_customer_username' =>
                            $subscription->hostingDeployment?->directadmin_customer_username,
                        'plesk_customer_id' => $subscription->hostingDeployment?->plesk_customer_id,
                        'plesk_customer_username' => $subscription->hostingDeployment?->plesk_customer_username,
                    ],
                ],
            );
            $subscription->hostingDeployment?->forceDelete();
        }

        $this->eventDispatcher->dispatch(
            new CreateHosting(
                $subscription->uuid,
                $subscription->technical_status,
                $subscription->customer->name,
                $subscription->customer->email,
                $subscription->domain,
                $subscription->customer,
                $subscription->product,
                $serverId,
            ),
        );
    }

    private function retrySitebuilderHosting(Subscription $subscription): void
    {
        Assert::notNull($subscription->domain, 'Provided subscription has no domain');

        $this->eventDispatcher->dispatch(new CreateSitebuilder(
            $subscription->customer->name,
            $subscription->customer->email,
            $subscription,
        ));
    }

    private function retryMailOnlyHosting(Subscription $subscription): void
    {
        Assert::notNull($subscription->domain, 'Provided subscription has no domain');

        $this->eventDispatcher->dispatch(new CreateMailOnlyHosting(
            $subscription->customer->name,
            $subscription->customer->email,
            $subscription,
        ));
    }

    private function retryResellerHosting(Subscription $subscription, ?int $serverId): void
    {
        $this->jobDispatcher->dispatch(
            new CreateResellerHostingJob(
                subscriptionUuid: $subscription->uuid,
                technicalStatus: $subscription->technical_status,
                contactPersonName: $subscription->customer->name,
                contactEmail: $subscription->customer->email,
                serverId: $serverId,
                customer: $subscription->customer,
                product: $subscription->product,
            ),
        );
    }
}
