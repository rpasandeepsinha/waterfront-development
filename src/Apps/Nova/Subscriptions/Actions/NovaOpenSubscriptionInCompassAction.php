<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaOpenSubscriptionInCompassAction extends Action
{
    public $onlyOnDetail = true;

    public $showInline = true;

    public $withoutConfirmation = true;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ConfigurationInterface $configuration,
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.open-in-compass');
    }

    /**
     * @param Collection<int, Subscription> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $subscription = $models->first();
        assert($subscription instanceof Subscription);

        $compassUrl = $this->configuration->getAsString('nova.compass_url');

        return self::openInNewTab(sprintf('%s/%s/%s', $compassUrl, 'subscription', $subscription->id));
    }
}
