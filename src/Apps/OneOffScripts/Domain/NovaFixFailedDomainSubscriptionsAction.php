<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Domain;

use Illuminate\Contracts\Bus\Dispatcher;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\OneOffScripts\NovaOneOffScriptAbstractAction;
use Waterfront\Apps\OneOffScripts\Domain\Jobs\DiagnoseFailedDomainSubscriptionJob;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

class NovaFixFailedDomainSubscriptionsAction extends NovaOneOffScriptAbstractAction
{
    public const SLUG = 'fix-failed-domain-subscriptions';

    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
        parent::__construct();
    }

    /** @return array<Field> */
    public function fields(NovaRequest $request): array
    {
        return [
            ...$this->getOneOffScriptInfoFields(),
            Boolean::make('Dry run', 'dry-run')->default(true),
        ];
    }

    public function handle(ActionFields $fields): ActionResponse
    {
        $dryRun = (bool) $fields->get('dry-run');

        $subscriptions = $this->subscriptionRepository->getFailedDomainSubscriptionsWithDeployment();

        $queuedCount = 0;

        foreach ($subscriptions as $subscription) {
            $this->dispatcher->dispatch(new DiagnoseFailedDomainSubscriptionJob(
                subscription: $subscription,
                dryRun: $dryRun,
                triggeredBy: self::SLUG,
            ));

            $queuedCount++;
        }

        $this->registerExecution();

        return ActionResponse::message(sprintf(
            'Queued %d failed domain subscriptions for async processing. Dry run: %s.',
            $queuedCount,
            $dryRun ? 'yes' : 'no',
        ));
    }

    protected function getOneOffScriptSlug(): string
    {
        return self::SLUG;
    }

    protected function getOneOffScriptTicketUrl(): string
    {
        return 'https://yh-jira.atlassian.net/browse/SWD-16482';
    }
}
