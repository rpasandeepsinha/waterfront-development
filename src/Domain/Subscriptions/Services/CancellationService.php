<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Domains\Jobs\EnableDomainAutoRenewJob;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Exceptions\AmountException;
use Waterfront\Domain\Subscriptions\Exceptions\CancelNotRevertedException;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCancelled;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCancelReverted;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class CancellationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
        private readonly Dispatcher $jobDispatcher,
        private readonly StoreNoteAction $storeNoteAction,
    ) {
    }

    /**
     * TODO Jira ticket WATER-5421 was created to look into a cleanup of
     *      this cancel function, because the logic has become a bit messy.
     */
    public function cancel(
        Subscription $subscription,
        SubscriptionCancelType $cancelType,
        SubscriptionCancelReason $cancelReason,
        bool $sendMail = true,
        ?CarbonImmutable $cancelTypeOtherDate = null,
        ?string $cancelNote = null,
        bool $saveNote = true,
    ): Subscription {
        $this->logger->info(sprintf(
            'Cancelling subscription %s (%s): %s',
            $subscription->domain ?? '',
            $subscription->uuid,
            $cancelType->value,
        ), [
            LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
            LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            LoggingContextKeys::META => ['cancel_type' => $cancelType->value],
        ]);

        $subscription->administrative_status = AdministrativeStatus::CANCELED->value;
        $subscription->cancel_date = CarbonImmutable::now();
        $subscription->cancel_reason = $cancelReason;
        $subscription->save();
        $subscription->refresh();

        if ($saveNote) {
            $generatedEndDate = $this->generateEndDateForCancellation($subscription, $cancelType, $cancelTypeOtherDate);
            $noteMessage = sprintf(
                'Subscription cancelled. %s, generated end date: %s',
                $cancelNote,
                $generatedEndDate->format(DateTimeFormat::DATE),
            );

            $this->storeNoteAction->execute($noteMessage, $subscription);
        }

        foreach ($subscription->children as $childSubscription) {
            if (in_array(
                $childSubscription->administrative_status,
                [
                    AdministrativeStatus::ARCHIVING->value,
                    ...AdministrativeStatus::administrativelyEnded(),
                ],
                true,
            )) {
                continue;
            }

            $this->logger->info(sprintf(
                'Cancelling child subscription because parent is cancelled %s (%s)',
                $childSubscription->domain ?? '',
                $childSubscription->uuid,
            ), [
                LoggingContextKeys::DOMAIN_NAME => $childSubscription->domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $childSubscription->uuid,
            ]);

            $childSubscription->administrative_status = AdministrativeStatus::CANCELED->value;
            $childSubscription->cancel_date = CarbonImmutable::now();
            $subscription->cancel_reason = $cancelReason;
            $childSubscription->save();
        }

        $this->cancelWithEndDate($subscription, $cancelType, $cancelTypeOtherDate);

        if ($subscription->product->isDomainProduct()) {
            $this->cancelRelatedRedirectSubscription($subscription, $cancelType);
        }

        if ($sendMail) {
            $this->sendConfirmationMailToCustomer($subscription, $cancelType);
        }

        return $subscription;
    }

    /**
     * @throws CancelNotRevertedException
     */
    public function revertCancel(Subscription $subscription): void
    {
        $revertedSubscriptions = new Collection([$subscription]);

        if (
            $subscription->administrative_status !== AdministrativeStatus::CANCELED->value
            || $subscription->cancel_date === null
        ) {
            throw CancelNotRevertedException::subscriptionNotCancelled($subscription->id, $subscription->uuid);
        }

        if ($subscription->end_date->isBefore(CarbonImmutable::today())) {
            throw CancelNotRevertedException::subscriptionEndDateExpired($subscription->id, $subscription->uuid);
        }

        if ($subscription->product->isDomainProduct() && $subscription->domainDeployment instanceof DomainDeployment) {
            $this->jobDispatcher->dispatch(new EnableDomainAutoRenewJob($subscription));
        }

        $this->revertSubscriptionCancel($subscription);
        $dnsSubscription = $this->revertDnsSubscriptionCancel($subscription);
        if ($dnsSubscription instanceof Subscription) {
            $revertedSubscriptions->push($dnsSubscription);
        }

        $freeRedirectSubscription = $this->revertFreeRedirectSubscriptionCancel($subscription);
        if ($freeRedirectSubscription instanceof Subscription) {
            $revertedSubscriptions->push($freeRedirectSubscription);
        }

        $this->sendCancelRevertedConfirmationMail($revertedSubscriptions);
    }

    public function cancelChildSubscriptions(string $parentUuid, int $amount): void
    {
        /** @var Subscription $parentSubscription */
        $parentSubscription = Subscription::where('uuid', $parentUuid)->first();
        $childSubscriptions = Subscription::where('parent_subscription_id', $parentSubscription->id)->where(
            'administrative_status',
            AdministrativeStatus::ACTIVE->value,
        )->get();

        if ($childSubscriptions->count() < $amount) {
            throw new AmountException($this->translator->translate('service.cancel.child-subscriptions.fail'));
        }

        $cancelOption = SubscriptionCancelType::CANCEL_END_DATE;

        for ($i = 0; $i < $amount; $i++) {
            $childSubscription = $childSubscriptions[$i];
            Assert::isInstanceOf($childSubscription, Subscription::class);
            $this->cancel($childSubscription, $cancelOption, SubscriptionCancelReason::REASON_CANCELLATION, false);
        }

        if ($childSubscriptions->count() === $amount) {
            $this->cancel($parentSubscription, $cancelOption, SubscriptionCancelReason::REASON_CANCELLATION, false);
        }
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    private function sendCancelRevertedConfirmationMail(Collection $subscriptions): void
    {
        $subscription = $subscriptions->first();
        assert($subscription instanceof Subscription);

        /** @var array<array{domainName: string, productDescription: string, productName: string, subscriptionEndDate: string, contractPeriod: int}> $formattedSubscriptions */
        $formattedSubscriptions = $subscriptions->map(
            fn (Subscription $subscription): array => [
                'domainName' => $subscription->domain ?? $subscription->uuid,
                'productDescription' => $subscription->product->description ?? '',
                'productName' => $subscription->product->name,
                'subscriptionEndDate' => $subscription->end_date->format('d M Y'),
                'contractPeriod' => $subscription->contract_period,
            ],
        )->toArray();

        $this->mailer->send(
            [$subscription->customer],
            new MailSubscriptionCancelReverted($formattedSubscriptions),
        );
    }

    private function revertSubscriptionCancel(Subscription $subscription): void
    {
        $subscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $subscription->cancel_date = null;
        $subscription->cancel_reason = null;
        $subscription->save();
    }

    private function revertDnsSubscriptionCancel(Subscription $subscription): ?Subscription
    {
        if (! $subscription->domainDeployment instanceof DomainDeployment) {
            return null;
        }

        $dnsSubscription = Subscription::query()
            ->whereProductGroupType(ProductGroupType::DNS)
            ->where('domain', $subscription->domain)
            ->where('customer_id', $subscription->customer_id)
            ->where('administrative_status', AdministrativeStatus::CANCELED->value)
            ->first();

        if ($dnsSubscription instanceof Subscription) {
            $this->revertSubscriptionCancel($dnsSubscription);

            return $dnsSubscription;
        }

        return null;
    }

    private function revertFreeRedirectSubscriptionCancel(Subscription $subscription): ?Subscription
    {
        if (! $subscription->domainDeployment instanceof DomainDeployment) {
            return null;
        }

        $freeRedirectSubscription = Subscription::query()
            ->whereProductGroupType(ProductGroupType::HOSTING)
            ->whereHas('product', function (Builder $query): void {
                $query->where('slug', ProductType::FREE_REDIRECT);
            })
            ->where('domain', $subscription->domain)
            ->where('customer_id', $subscription->customer_id)
            ->where('administrative_status', AdministrativeStatus::CANCELED->value)
            ->first();

        if ($freeRedirectSubscription instanceof Subscription) {
            $this->revertSubscriptionCancel($freeRedirectSubscription);

            return $freeRedirectSubscription;
        }

        return null;
    }

    private function cancelRelatedRedirectSubscription(
        Subscription $subscription,
        SubscriptionCancelType $cancelType,
    ): void {
        /** @var Subscription|null $redirectSubscription */
        $redirectSubscription = Subscription::query()
            ->whereProductGroupType(ProductGroupType::REDIRECT)
            ->where('customer_id', $subscription->customer_id)
            ->where('domain', $subscription->domain)
            ->whereNotIn('administrative_status', [
                AdministrativeStatus::CANCELED->value,
                AdministrativeStatus::ARCHIVING->value,
                ...AdministrativeStatus::administrativelyEnded(),
            ])
            ->first();

        if ($redirectSubscription instanceof Subscription) {
            $this->cancel(
                subscription: $redirectSubscription,
                cancelType: $cancelType,
                cancelReason: SubscriptionCancelReason::REASON_CANCELLATION,
                saveNote: false,
            );
        }
    }

    private function generateEndDateForCancellation(
        Subscription $subscription,
        SubscriptionCancelType $cancelType,
        ?CarbonImmutable $endDate,
    ): CarbonImmutable {
        if ($cancelType !== SubscriptionCancelType::CANCEL_OTHER) {
            return $subscription->end_date;
        }

        $endDate ??= CarbonImmutable::now();

        return $endDate;
    }

    private function cancelWithEndDate(
        Subscription $subscription,
        SubscriptionCancelType $cancelType,
        ?CarbonImmutable $endDate,
    ): void {
        if ($cancelType !== SubscriptionCancelType::CANCEL_OTHER) {
            return;
        }

        $generatedEndDate = $this->generateEndDateForCancellation(
            $subscription,
            $cancelType,
            $endDate,
        );

        $subscription->end_date = $generatedEndDate;
        $subscription->save();

        foreach ($subscription->children as $childSubscription) {
            if (in_array(
                $childSubscription->administrative_status,
                [
                    AdministrativeStatus::ARCHIVING->value,
                    ...AdministrativeStatus::administrativelyEnded(),
                ],
                true,
            )) {
                continue;
            }

            $childSubscription->end_date = $generatedEndDate;
            $childSubscription->save();
        }
    }

    private function sendConfirmationMailToCustomer(
        Subscription $subscription,
        SubscriptionCancelType $cancelType,
    ): void {
        $this->mailer->send(
            [$subscription->customer],
            new MailSubscriptionCancelled(
                $subscription->product->productGroup->name,
                $subscription->product->name,
                $subscription->domain ?? '',
                $subscription->end_date->format('d M Y'),
                $cancelType->value,
            ),
        );
    }
}
