<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Listeners;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use SandwaveIo\Office365\Exception\Office365Exception;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Microsoft365\Events\TerminateMicrosoft365;
use Waterfront\Domain\Microsoft365\Mailer\Microsoft365SeatsChanged;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCancelled;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

class Microsoft365TerminationListener
{
    public function __construct(
        private readonly Microsoft365Service $microsoft365Service,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(TerminateMicrosoft365 $event): void
    {
        $subscription = $event->subscription;
        $microsoft365Deployment = $this->findMicrosoft365Subscription($subscription);

        if (! $microsoft365Deployment instanceof Microsoft365Deployment) {
            Log::error(sprintf(
                '%s::handle - Termination for Microsoft 365 with subscription_uuid {%s} has failed because it has no deployment.',
                self::class,
                $subscription->uuid,
            ));

            return;
        }

        $allNotDeletedChildren = $subscription->children->filter(
            fn (Subscription $subscription) => (
                $subscription->administrative_status !== AdministrativeStatus::ARCHIVED->value
            ),
        );
        $allNotDeletedChildrenCount = $allNotDeletedChildren->count();

        $cancelledExpiredChildren = $subscription->children->filter(
            fn (Subscription $subscription) => (
                $subscription->administrative_status === AdministrativeStatus::EXPIRED->value
                && $subscription->end_date < CarbonImmutable::now()
                && $subscription->termination_date <= CarbonImmutable::now()
            ),
        );
        $cancelledExpiredChildrenCount = $cancelledExpiredChildren->count();

        // The parent subscription is cancelled and expired
        if (
            $subscription->administrative_status === AdministrativeStatus::ARCHIVED->value
            && $subscription->end_date < CarbonImmutable::now()
        ) {
            $this->terminateOrder($microsoft365Deployment, $subscription, $allNotDeletedChildren);

            return;
        }

        // All child subscriptions are cancelled and expired
        if ($allNotDeletedChildrenCount === $cancelledExpiredChildrenCount) {
            $this->terminateOrder($microsoft365Deployment, $subscription, $allNotDeletedChildren);

            return;
        }

        if ($cancelledExpiredChildrenCount > 0 && $this->checkKpnStartDateForModify($microsoft365Deployment)) {
            $this->logger->info(
                sprintf(
                    'Modifying m365 order for subscription {subscription.id}: {domain.name} removing %d seats',
                    $cancelledExpiredChildrenCount,
                ),
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                ],
            );
            try {
                $this->microsoft365Service->modifyOrder(
                    (int) $microsoft365Deployment->kpn_order_id,
                    -$cancelledExpiredChildrenCount,
                );
                foreach ($cancelledExpiredChildren as $canceledChild) {
                    $canceledChild->administrative_status = AdministrativeStatus::ARCHIVING->value;
                    $canceledChild->save();
                }

                $this->sendSeatsChangedEmail(
                    $subscription,
                    $allNotDeletedChildrenCount,
                    $allNotDeletedChildrenCount - $cancelledExpiredChildrenCount,
                );
            } catch (Office365Exception $e) {
                Log::error(sprintf(
                    'Error while modifying order for KPN order_id: [%s] with amount: [%s]. With exception message: %s',
                    (int) $microsoft365Deployment->kpn_order_id,
                    -$cancelledExpiredChildrenCount,
                    $e->getMessage(),
                ));
            }
        }
    }

    private function findMicrosoft365Subscription(Subscription $subscription): ?Microsoft365Deployment
    {
        $microsoft365Deployment = Microsoft365Deployment::where('subscription_id', $subscription->id)->first();

        if ($microsoft365Deployment === null) {
            Log::error(sprintf(
                'Missing Microsoft365 deployment for %s (%d)',
                $subscription->uuid,
                $subscription->id,
            ));

            return null;
        }

        return $microsoft365Deployment;
    }

    private function sendCancelMail(Subscription $subscription): void
    {
        $this->mailer->send([$subscription->customer], new MailSubscriptionCancelled(
            $subscription->product->productGroup->name,
            $subscription->product->name,
            $subscription->domain ?? '',
            $subscription->end_date->format('d M Y'),
            'cancel_end_date',
        ));
    }

    private function sendSeatsChangedEmail(Subscription $subscription, int $old_seats, int $new_seats): void
    {
        $this->mailer->send([$subscription->customer], new Microsoft365SeatsChanged(
            $subscription->product->productGroup->name,
            $subscription->product->name,
            $old_seats,
            $new_seats,
        ));
    }

    /**
     * @param Collection<int, Subscription> $allNotDeletedChildren
     */
    private function terminateOrder(
        Microsoft365Deployment $microsoft365Deployment,
        Subscription $subscription,
        Collection $allNotDeletedChildren,
    ): void {
        $this->logger->info('Terminating m365 order for subscription {subscription.id}: {domain.name}', [
            LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
            LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
        ]);
        try {
            $this->microsoft365Service->terminateOrder($microsoft365Deployment);
            $subscription->administrative_status = AdministrativeStatus::ARCHIVING->value;
            $subscription->save();

            foreach ($allNotDeletedChildren as $child) {
                $child->administrative_status = AdministrativeStatus::ARCHIVING->value;
                $child->save();
            }

            $this->sendCancelMail($subscription);
        } catch (Office365Exception $e) {
            Log::error(
                sprintf(
                    'Error while terminating order for KPN order_id: [%s]. With exception message: %s',
                    $microsoft365Deployment->kpn_order_id,
                    $e->getMessage(),
                ),
            );
        }
    }

    private function checkKpnStartDateForModify(Microsoft365Deployment $microsoft365Deployment): bool
    {
        // Since the sync hasn't runned yet all kpn_start_date's are null. For now we will accept a modify.
        if ($microsoft365Deployment->kpn_start_date === null) {
            return true;
        }

        return $microsoft365Deployment->kpn_start_date->diffInDays(CarbonImmutable::now(), true) < 7;
    }
}
