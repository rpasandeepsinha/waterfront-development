<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Waterfront\Domain\Subscriptions\Exceptions\UnableToSuspendSubscriptionException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\UnsuspendSubscriptionService;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaUnsuspendSubscriptionAction extends NovaSubscriptionAction
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly UnsuspendSubscriptionService $unSuspendSubscriptionAction,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.unsuspend_subscription');
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    public function handle(ActionFields $fields, Collection $subscriptions): ActionResponse|static
    {
        foreach ($subscriptions as $subscription) {
            try {
                $this->unSuspendSubscriptionAction->execute($subscription);
            } catch (UnableToSuspendSubscriptionException) {
                // @ignoreException
            }
        }

        return self::message($this->translator->translate('nova-action.success.subscription_unsuspension_successful'));
    }
}
