<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\FreeRedirectActions;

use Illuminate\Contracts\Bus\Dispatcher;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\NovaRequest;
use Psr\Log\LoggerInterface;
use Waterfront\Apps\Nova\OneOffScripts\NovaOneOffScriptAbstractAction;
use Waterfront\Domain\Redirects\Jobs\UpgradeFreeRedirectJob;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class NovaUpgradeFreeRedirectAction extends NovaOneOffScriptAbstractAction
{
    public const string SLUG = 'upgrade-free-redirect';

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly Dispatcher $jobDispatcher,
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
            Boolean::make('Dry run', 'dry-run')->withMeta(['value' => true]),
            Number::make('Batch amount', 'amount'),
        ];
    }

    public function handle(ActionFields $fields): ActionResponse|static
    {
        $dryRun = (bool) $fields['dry-run'];
        Assert::integerish($fields['amount']);
        $limit = (int) $fields['amount'];

        $mode = $dryRun ? 'dry-run' : 'execution';
        $this->logger->debug(
            sprintf(
                'Executing one-time script %s in %s mode',
                $this->getOneOffScriptSlug(),
                $mode,
            ),
            [
                LoggingContextKeys::ONE_OFF_SCRIPT => $this->getOneOffScriptSlug(),
            ],
        );

        $freeRedirectSubscriptions = $this->subscriptionRepository->getActiveFreeRedirectSubscriptions($limit);

        if (! $dryRun) {
            foreach ($freeRedirectSubscriptions as $freeRedirectSubscription) {
                $this->jobDispatcher->dispatch(new UpgradeFreeRedirectJob($freeRedirectSubscription));
            }

            return self::message(sprintf(
                'Found %d redirect deployments which might be upgraded. Upgrades will be done async',
                count($freeRedirectSubscriptions),
            ));
        }

        return self::message(sprintf(
            'The dry run found %d redirect deployments which still can be checked for upgrade.',
            count($freeRedirectSubscriptions),
        ));
    }

    protected function getOneOffScriptSlug(): string
    {
        return self::SLUG;
    }

    protected function getOneOffScriptTicketUrl(): string
    {
        return 'https://yh-jira.atlassian.net/browse/SWD-6923';
    }
}
