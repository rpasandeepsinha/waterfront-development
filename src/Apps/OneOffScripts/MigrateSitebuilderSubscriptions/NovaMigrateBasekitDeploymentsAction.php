<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\MigrateSitebuilderSubscriptions;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\NovaRequest;
use Psr\Log\LoggerInterface;
use Waterfront\Apps\Nova\OneOffScripts\NovaOneOffScriptAbstractAction;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\BasekitSubscriptionRepository;
use Waterfront\Domain\Sitebuilder\Enums\BasekitMigrationEligibility;
use Waterfront\Domain\Sitebuilder\Services\BasekitMigrationService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

class NovaMigrateBasekitDeploymentsAction extends NovaOneOffScriptAbstractAction
{
    public const SLUG = 'migrate-basekit-deployments';

    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly LoggerInterface $logger,
        private readonly BasekitSubscriptionRepository $basekitSubscriptionRepository,
        private readonly BasekitMigrationService $basekitMigrationService,
    ) {
        parent::__construct();
    }

    /** @return array<Field> */
    public function fields(NovaRequest $request): array
    {
        return [
            ...$this->getOneOffScriptInfoFields(),
            Boolean::make('Dry run', 'dry-run')->default(true),
            Number::make('Limit (0 = all)', 'limit')->default(0)->min(0),
            Number::make('Eligible subscriptions (estimate)')
                ->default($this->basekitSubscriptionRepository->countBasekitSubscriptions())
                ->readonly(),
        ];
    }

    public function handle(ActionFields $fields): ActionResponse
    {
        $isDryRun = $fields->boolean('dry-run');
        $limit = $fields->integer('limit');

        $this->logger->debug(
            sprintf('Executing one-time script %s', $this->getOneOffScriptSlug()),
            [
                LoggingContextKeys::ONE_OFF_SCRIPT => $this->getOneOffScriptSlug(),
                LoggingContextKeys::META => ['dry-run' => $isDryRun, 'limit' => $limit],
            ]
        );

        $this->registerExecution();

        if ($isDryRun) {
            $counters = new BasekitMigrationEligibilityCounters();
            $this->scanAndCountEligibility($limit, $counters);

            $this->logger->info('Basekit migration dry-run summary', [
                LoggingContextKeys::ONE_OFF_SCRIPT => $this->getOneOffScriptSlug(),
                LoggingContextKeys::META => array_merge(
                    ['dry-run' => true, 'limit' => $limit],
                    $counters->logContext()
                ),
            ]);

            return self::message(sprintf(
                'Dry run: %d eligible | skipped already=%d, not_sitebuilder=%d, no_package_ref=%d, missing_data=%d',
                $counters->getCounter(BasekitMigrationEligibility::ELIGIBLE),
                $counters->getCounter(BasekitMigrationEligibility::ALREADY_MIGRATED),
                $counters->getCounter(BasekitMigrationEligibility::NOT_SITEBUILDER),
                $counters->getCounter(BasekitMigrationEligibility::NO_PACKAGE_REFERENCE),
                $counters->getCounter(BasekitMigrationEligibility::MISSING_DATA),
            ));
        }

        $dispatched = $this->dispatchEligibleJobs($limit);
        return self::message(sprintf('Queued %d migration job(s).', $dispatched));
    }

    protected function getOneOffScriptSlug(): string
    {
        return self::SLUG;
    }

    protected function getOneOffScriptTicketUrl(): string
    {
        return 'https://yh-jira.atlassian.net/browse/SWD-9782';
    }

    private function scanAndCountEligibility(int $limit, BasekitMigrationEligibilityCounters $counters): void
    {
        $continue = true;

        $this->basekitSubscriptionRepository->chunkBasekitSubscriptions(
            function (Collection $subscriptions) use ($limit, $counters, &$continue): bool {
                /** @var Subscription $subscription */
                foreach ($subscriptions as $subscription) {
                    if ($limit > 0 && $counters->getCounter(BasekitMigrationEligibility::ELIGIBLE) >= $limit) {
                        $continue = false;
                        break;
                    }

                    $outcome = $this->basekitMigrationService->assessEligibility($subscription);
                    $counters->increment($outcome);
                }

                return $continue;
            }
        );
    }

    private function dispatchEligibleJobs(int $limit): int
    {
        $dispatched = 0;
        $continue = true;

        $this->basekitSubscriptionRepository->chunkBasekitSubscriptions(
            function (Collection $subscriptions) use (&$dispatched, $limit, &$continue): bool {
                /** @var Subscription $subscription */
                foreach ($subscriptions as $subscription) {
                    if ($limit > 0 && $dispatched >= $limit) {
                        $continue = false;
                        break;
                    }

                    $eligibility = $this->basekitMigrationService->assessEligibility($subscription);
                    if ($eligibility !== BasekitMigrationEligibility::ELIGIBLE) {
                        continue;
                    }

                    $this->dispatcher->dispatch(
                        new MigrateBasekitSubscriptionJob($this->getOneOffScriptSlug(), $subscription->uuid)
                    );
                    $dispatched++;
                }

                return $continue;
            }
        );

        return $dispatched;
    }
}
