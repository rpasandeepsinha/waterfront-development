<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\RemoveStrayParkingDns;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\NovaRequest;
use Psr\Log\LoggerInterface;
use Waterfront\Apps\Nova\OneOffScripts\NovaOneOffScriptAbstractAction;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class NovaRemoveStrayParkingDnsAction extends NovaOneOffScriptAbstractAction
{
    public const string SLUG = 'remove-stray-parking-dns';

    private const int MONTHS_LOOKBACK = 4;

    public function __construct(
        private readonly Dispatcher $jobDispatcher,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    /**
     * @return array<Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ...$this->getOneOffScriptInfoFields(),
            Boolean::make('Dry run', 'dry-run')
                ->withMeta(['value' => true]),
            Number::make('Batch amount', 'amount')->default(500),
        ];
    }

    public function handle(ActionFields $fields): ActionResponse|static
    {
        $dryRun = (bool) $fields['dry-run'];
        Assert::integerish($fields['amount']);
        $limit = (int) $fields['amount'];

        $domains = $this->subscriptionRepository->getRecentDomainNames(
            CarbonImmutable::now()->subMonths(self::MONTHS_LOOKBACK),
            $limit
        );

        $this->logger->debug(
            'Dispatching stray parking DNS check for recent domains',
            [
                LoggingContextKeys::ONE_OFF_SCRIPT => self::SLUG,
                LoggingContextKeys::META => [
                    'dry_run' => $dryRun,
                    'count' => count($domains),
                ],
            ]
        );

        foreach ($domains as $domain) {
            $this->jobDispatcher->dispatch(new RemoveStrayParkingDnsJob($domain, $dryRun));
        }

        if (! $dryRun) {
            return self::message(sprintf(
                'Dispatched %d domains to check and remove stray parking DNS records asynchronously.',
                count($domains)
            ));
        }

        return self::message(sprintf(
            'Dry run: dispatched %d domains to check for stray parking DNS records asynchronously. Nothing will be removed.',
            count($domains)
        ));
    }

    protected function getOneOffScriptSlug(): string
    {
        return self::SLUG;
    }

    protected function getOneOffScriptTicketUrl(): string
    {
        return 'https://yh-jira.atlassian.net/browse/SWD-17022';
    }
}
