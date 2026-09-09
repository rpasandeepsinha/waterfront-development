<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Services;

use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Provision\DTO\ProvisioningFilteredResult;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Sitebuilder\Requests\TerminateSitebuilderContextRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\UpdateSitebuilderRequest;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Exceptions\TerminateException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Transfers\Mailer\MailTransferAwayCompleted;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

readonly class SubscriptionTerminateService
{
    public function __construct(
        private LoggerInterface $logger,
        private MailerInterface $mailer,
        private SubscriptionRepository $subscriptionRepository,
        private DeprovisionService $deprovisionService,
        private ProvisionGateway $provisioningGateway,
    ) {
    }

    /**
     * @throws TerminateException
     */
    public function terminate(Subscription $subscription): void
    {
        try {
            Assert::null($subscription->parent_subscription_id);
        } catch (InvalidArgumentException) {
            throw TerminateException::parentSubscriptionExpected();
        }

        $this->logger->info(
            'Terminating subscription {subscription.uuid}: {domain.name}',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain ?? '',
            ]
        );

        // Microsoft 365 service knows how to handle parent or child cancellations
        if ($subscription->product->productGroup->slug === ProductGroupType::MICROSOFT_365) {
            $this->archive($subscription);
            return;
        }

        if ($subscription->product->productGroup->slug === ProductGroupType::HOSTING && $subscription->product->isSitebuilderProduct()) {
            $this->terminateSitebuilder($subscription);
            return;
        }

        // If the parent subscription is cancelled and the end date is expired, cancel all children
        // Otherwise, check each child if they are expired
        if ($this->subscriptionRepository->isExpired($subscription)) {
            foreach ($subscription->children as $child) {
                $this->archive($child);
            }

            $this->archive($subscription);
            return;
        }

        foreach ($subscription->children as $child) {
            if ($this->subscriptionRepository->isExpired($child)) {
                $this->archive($child);
            }
        }
    }

    public function endTransferredSubscription(string $domain): void
    {
        $subscription = $this->subscriptionRepository->getSubscriptionByDomainAndGroup($domain, ProductGroupType::EXTENSION);
        $this->logger->info(
            "Ending subscription because it's transferred away.",
            [LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id]
        );
        $subscription->technical_status = TechnicalStatus::DELETED->value;
        $subscription->administrative_status = AdministrativeStatus::CANCELED->value;
        $subscription->end_date = CarbonImmutable::now();
        $subscription->cancel_date = CarbonImmutable::now();
        $subscription->cancel_reason = SubscriptionCancelReason::REASON_DOMAIN_TRANSFERRED_AWAY;
        $subscription->save();

        $this->terminate($subscription->parent ?? $subscription);

        $this->mailer->send([$subscription->customer], new MailTransferAwayCompleted($domain));
    }

    private function terminateSitebuilder(Subscription $parentSubscription): void
    {
        $query = new ProvisioningResultQueryFilters(tag: Uuid::fromString($parentSubscription->uuid), requestType: ProvisionType::SITEBUILDER);
        $provisioningResults = $this->provisioningGateway->fetch($query, 1);

        if (count($provisioningResults) === 0) {
            $this->archive($parentSubscription);
            return;
        }

        //So now we have the parent, we can see whether we only need to terminate a certain add-on, multiple add-ons.
        $provCreatedRequest = $provisioningResults->filter(fn (ProvisioningFilteredResult $request) => $request->requestType === ProvisionType::SITEBUILDER && $request->requestName === ProvisionRequestName::CREATE_SITEBUILDER)->first();
        if ($provCreatedRequest === null) {
            return;
        }

        // Let's start with the parent.
        if ($parentSubscription->end_date <= CarbonImmutable::now()) {
            $request = new TerminateSitebuilderContextRequest(Uuid::fromString($parentSubscription->uuid));
            $request->tag = Uuid::fromString($parentSubscription->uuid);

            $result = $this->provisioningGateway->request($request);
            if ($result->succeeded) {
                $parentSubscription->technical_status = TechnicalStatus::DELETED->value;
                $parentSubscription->administrative_status = AdministrativeStatus::ARCHIVED->value;
                $parentSubscription->save();
                return;
            }

            $this->logger->error(
                sprintf(
                    'Failed to terminate deployment for %s (%d)',
                    $parentSubscription->domain ?? '',
                    $parentSubscription->id
                ),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $parentSubscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $parentSubscription->domain,
                    LoggingContextKeys::EXCEPTION => $result->exception,
                ]
            );

            $parentSubscription->technical_status = TechnicalStatus::DELETING_FAILED->value;
            $parentSubscription->administrative_status = AdministrativeStatus::ARCHIVED->value;
            $parentSubscription->save();
        }

        $references = [];
        $parentSubscriptionSpec = $parentSubscription->product->productSpecs()->where('name', ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value)->first();

        if ($parentSubscriptionSpec === null) {
            $this->logger->error(
                sprintf(
                    'Can not terminate children of %s (%d) because parent doesnot have product spec',
                    $parentSubscription->domain ?? '',
                    $parentSubscription->id
                ),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $parentSubscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $parentSubscription->domain,
                ]
            );
            return;
        }

        $references[] = (int) $parentSubscriptionSpec->value;

        //If the parent is not due for cancellation, we should check whether there are children due for termination
        $children = $parentSubscription->children;
        $childrenWhichAreNotGoingToBeTerminated = $children->filter(fn (Subscription $subscription) => ! $this->subscriptionRepository->isExpired($subscription));

        // If there are no child subscriptions or all children are not going to be terminated, we can stop here.
        if (count($children) === 0 || count($children) === count($childrenWhichAreNotGoingToBeTerminated)) {
            return;
        }

        if (count($childrenWhichAreNotGoingToBeTerminated) > 0) {
            foreach ($childrenWhichAreNotGoingToBeTerminated as $child) {
                $baseKitSpec = $child->product->productSpecs()->where('name', ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value)->first();
                if ($baseKitSpec !== null) {
                    $references[] = (int) $baseKitSpec->value;
                }
            }
        }

        //Let's build the Update request now.
        $request = new UpdateSitebuilderRequest(
            tagUuid: Uuid::fromString($parentSubscription->uuid),
            context: Uuid::fromString($parentSubscription->uuid),
            packages: $references,
            contractPeriod: $parentSubscription->contract_period
        );

        $result = $this->provisioningGateway->request($request);

        if ($result->failed) {
            $this->logger->error(
                sprintf(
                    'Failed to terminate sitebuilder addons for %s (Parent id: %d)',
                    $parentSubscription->domain ?? '',
                    $parentSubscription->id
                ),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $parentSubscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $parentSubscription->domain,
                    LoggingContextKeys::EXCEPTION => $result->exception ?? 'No exception thrown',
                ]
            );
        }

        $childrenWhichWillBeTerminated = $children->filter(fn (Subscription $subscription) => $this->subscriptionRepository->isExpired($subscription));

        foreach ($childrenWhichWillBeTerminated as $child) {
            $child->administrative_status = AdministrativeStatus::ARCHIVED->value;
            $child->save();
        }
    }

    private function archive(Subscription $subscription): void
    {
        $this->deprovisionService->deprovision($subscription);

        if ($subscription->product->productGroup->slug !== ProductGroupType::MICROSOFT_365) {
            $subscription->administrative_status = AdministrativeStatus::ARCHIVED->value;
            $subscription->save();
        }
    }
}
