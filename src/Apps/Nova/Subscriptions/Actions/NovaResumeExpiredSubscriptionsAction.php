<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Actions;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Subscriptions\Jobs\ResumeSubscriptionJob;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaResumeExpiredSubscriptionsAction extends NovaSubscriptionAction
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly TranslatorInterface $translator,
    ) {
        $this->canSee(fn (NovaRequest $request): bool => $this->onlyForExpiredSubscriptions($request));
        $this->confirmText = $translator->translate('nova-action.subscription.resume.description');
        $this->modalSize = '7xl';
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.subscription.resume.action_name');
    }

    /**
     * @return array<int, NovaBoolField>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            NovaBoolField::make(
                $this->translator->translate('nova-action.subscription.resume.quarantaine_costs.label'),
                'create_invoice',
            )->help(
                $this->translator->translate('nova-action.subscription.resume.quarantaine_costs.confirmtext'),
            ),
            NovaBoolField::make(
                '',
                'confirm_action',
            )->help($this->translator->translate('nova-action.subscription.resume.execute.confirmation_checkbox')),
        ];
    }

    /**
     * @param Collection<int, Subscription> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        if (! $fields->confirm_action) {
            return self::danger(
                $this->translator->translate('nova-action.subscription.resume.execute.confirmation_checkbox_error'),
            );
        }

        /** @var Collection<int, Subscription> $subscriptions */
        $subscriptions = $models;

        $skipInvoiceQuarantaineCosts = $fields->create_invoice;

        $subscriptionCollection = new SupportCollection();

        foreach ($subscriptions as $subscription) {
            if ($subscription->parent_subscription_id !== null) {
                $subscriptionCollection->add($subscription->parent);
            }

            $subscriptionCollection->add($subscription);
        }

        $filteredCollection = $subscriptionCollection->unique('id');

        $filteredCollection->each(function (Subscription $subscription) use ($skipInvoiceQuarantaineCosts): void {
            $this->dispatcher->dispatch(new ResumeSubscriptionJob($subscription, ! $skipInvoiceQuarantaineCosts));
        });

        return Action::message($this->translator->translate('nova-action.subscription.resume.success'));
    }
}
