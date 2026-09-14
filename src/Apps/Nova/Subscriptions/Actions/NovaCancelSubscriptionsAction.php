<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Actions;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use JsonException;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\DNS\Actions\ChangeDnsAction;
use Waterfront\Domain\DNS\Exceptions\DnsChangeException;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Exceptions\DowngradeCancelException;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionChangeException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\CancellationService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class NovaCancelSubscriptionsAction extends NovaSubscriptionAction
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SubscriptionChangeService $changeService,
        private readonly ChangeDnsAction $changeDnsAction,
        private readonly DnsProductSpecRepository $dnsProductSpecRepository,
        private readonly LoggerInterface $logger,
        private readonly CancellationService $cancellationService,
    ) {
        $this->canSee(
            fn (NovaRequest $request): bool => $this->onlyForSingleCustomer($request),
        );
    }

    /**
     * Get the displayable name of the action.
     */
    public function name(): string
    {
        return $this->translator->translate('nova-action.cancel_subscriptions.name');
    }

    /**
     * Perform the action on the given models.
     *
     * @param Collection<int, Subscription> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $failedCancellations = [];

        foreach ($models as $subscription) {
            if (
                $subscription->administrative_status === AdministrativeStatus::CANCELED->value
                || $subscription->administrative_status === AdministrativeStatus::ARCHIVING->value
                || in_array($subscription->administrative_status, AdministrativeStatus::administrativelyEnded(), true)
            ) {
                $failedCancellations[] = $subscription->uuid;
                continue;
            }

            $parentExistsInModels = $models->where('id', $subscription->parent_subscription_id)->first()
            instanceof Subscription;

            try {
                if ($this->dnsProductSpecRepository->isPremiumDns($subscription->product) && ! $parentExistsInModels) {
                    $downgradeProduct = $this->changeService->getAvailableDowngradeWhenCanceled($subscription);
                    $this->changeDnsAction->execute($subscription, ProductChangeType::DOWNGRADE);
                    $this->changeService->change(
                        changeType: ProductChangeType::DOWNGRADE,
                        subscription: $subscription,
                        newProduct: $downgradeProduct,
                    );
                    continue;
                }
            } catch (
                PdnsResponseException|GuzzleException|SubscriptionChangeException|DnsChangeException|JsonException|DowngradeCancelException $e
            ) {
                $failedCancellations[] = $subscription->uuid;

                $this->logger->warning(
                    'Nova subscription cancellation failed for subscription with uuid : {subscription.uuid}',
                    [
                        LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                        LoggingContextKeys::EXCEPTION => $e,
                    ],
                );
                continue;
            }

            $this->cancellationService->cancel(
                subscription: $subscription,
                cancelType: SubscriptionCancelType::CANCEL_END_DATE,
                cancelReason: SubscriptionCancelReason::REASON_CANCELLATION,
                sendMail: false,
            );
        }

        if (count($failedCancellations) >= 1) {
            return self::danger(
                $this->translator->translate(
                    'nova-action.cancel_subscriptions.failure',
                    ['subscriptions' => $this->formatFailedCancellations($failedCancellations)],
                ),
            );
        }

        return self::message($this->translator->translate('nova-action.cancel_subscriptions.success'));
    }

    /**
     * @param string[] $failedCancellations
     */
    private function formatFailedCancellations(array $failedCancellations): string
    {
        return implode(',<br />', $failedCancellations);
    }
}
