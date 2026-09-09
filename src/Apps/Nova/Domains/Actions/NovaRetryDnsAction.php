<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Domains\Actions;

use Exception;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaSubscriptionAction;
use Waterfront\Domain\DNS\Actions\RedeployDnsAction;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaRetryDnsAction extends NovaSubscriptionAction
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RedeployDnsAction $action,
    ) {
        $this->canSee(
            fn (NovaRequest $request): bool =>
                $this->onlyForSubscriptionsWithProductGroupType($request, ProductGroupType::DNS)
        );
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.retry_dns');
    }

    /**
     * @param Collection<int, Subscription> $models
     *
     * @throws Exception
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $subscription = $models->firstOrFail();
        $this->action->execute($subscription);

        return Action::message($this->translator->translate('nova-action.success.retried_dns'));
    }
}
