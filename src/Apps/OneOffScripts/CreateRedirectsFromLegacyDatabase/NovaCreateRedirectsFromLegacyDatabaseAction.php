<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\CreateRedirectsFromLegacyDatabase;

use Illuminate\Bus\Dispatcher;
use Illuminate\Contracts\Redis\Factory;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\NovaRequest;
use Psr\Log\LoggerInterface;
use Waterfront\Apps\Nova\OneOffScripts\NovaOneOffScriptAbstractAction;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

class NovaCreateRedirectsFromLegacyDatabaseAction extends NovaOneOffScriptAbstractAction
{
    public const string SLUG = 'create-redirects-from-legacy-database';
    public const string REDIS_PROCESSED_KEY = 'redirects-migration:processed_subscription_uuids';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Dispatcher $busDispatcher,
        private readonly Factory $redisFactory,
    ) {
        parent::__construct();
    }

    /** @return array<Field> */
    public function fields(NovaRequest $request): array
    {
        $processed = $this->redisFactory->connection()->scard(self::REDIS_PROCESSED_KEY);

        return [
            ...$this->getOneOffScriptInfoFields(),
            Boolean::make('Dry run', 'dry-run')->default(true),
            Boolean::make('Fix dns for existing', 'only-existing')->default(false),
            Number::make('Limit subscriptions', 'limit')->default(200),
            Heading::make("<h3 class=\"text-xl\">Currently processed: {$processed}</h3><hr />")
                ->asHtml(),
        ];
    }

    public function handle(ActionFields $fields): ActionResponse
    {
        $isDryRun = $fields->boolean('dry-run');
        $onlyExisting = $fields->boolean('only-existing');
        $limit = $fields->integer('limit', 200);

        $this->logger->debug(
            sprintf('Executing one-time script %s', $this->getOneOffScriptSlug()),
            [
                LoggingContextKeys::ONE_OFF_SCRIPT => $this->getOneOffScriptSlug(),
                LoggingContextKeys::META => [
                    'dry-run' => $isDryRun,
                    'only-existing' => $onlyExisting,
                    'limit' => $limit,
                ],
            ]
        );

        $redirectSubscriptionQuery = Subscription::whereNotIn('administrative_status', AdministrativeStatus::getIneligibleForSuspension())
            ->where('technical_status', TechnicalStatus::OK)
            ->whereHas('product', function (Builder $productQuery) {
                $productQuery->whereHas(
                    'productGroup',
                    fn (Builder $productGroupQuery) => $productGroupQuery->where('slug', ProductGroupType::REDIRECT)
                );
            });

        if ($isDryRun) {
            $redirectSubscriptionQuery->limit(30);
        }

        $processed = $this->redisFactory->connection()->sMembers(self::REDIS_PROCESSED_KEY);

        if ($onlyExisting) {
            $alreadyProcessedSubscriptions = $redirectSubscriptionQuery->get()
                ->reject(fn (Subscription $subscription) => ! in_array($subscription->uuid, $processed, true))
                ->take($limit);

            $alreadyProcessedSubscriptions->each(
                fn (Subscription $subscription) => $this->busDispatcher->dispatch(
                    new CreateRedirectsFromLegacyDatabaseJob(
                        dryRun: $isDryRun,
                        subscription: $subscription
                    )
                )
            );

            return self::message(sprintf('One-off script dispatched on already parsed subscriptions: %d jobs to queue.', $alreadyProcessedSubscriptions->count()));
        }

        $nonProcessedSubscriptions = $redirectSubscriptionQuery->get()
            ->reject(fn (Subscription $subscription) => in_array($subscription->uuid, $processed, true))
            ->take($limit);

        $nonProcessedSubscriptions->each(
            fn (Subscription $subscription) => $this->busDispatcher->dispatch(
                new CreateRedirectsFromLegacyDatabaseJob(
                    dryRun: $isDryRun,
                    subscription: $subscription
                )
            )
        );

        return self::message(sprintf('One-off script dispatched %d jobs to queue.', $nonProcessedSubscriptions->count()));
    }

    protected function getOneOffScriptSlug(): string
    {
        return self::SLUG;
    }

    protected function getOneOffScriptTicketUrl(): string
    {
        return 'https://yh-jira.atlassian.net/browse/SWD-16514';
    }
}
