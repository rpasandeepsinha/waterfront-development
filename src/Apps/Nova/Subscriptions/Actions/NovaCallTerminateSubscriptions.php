<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Actions;

use Artisan;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Waterfront\Apps\Console\Commands\Subscriptions\TerminateSubscriptions;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaCallTerminateSubscriptions extends NovaSubscriptionAction
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
        $this->standalone();
        $this->onlyOnIndex();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.call-terminate-subscriptions');
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    public function handle(ActionFields $fields, Collection $subscriptions): ActionResponse|static
    {
        Artisan::call(TerminateSubscriptions::class);

        return self::message('TerminateSubscriptions has been called.');
    }
}
