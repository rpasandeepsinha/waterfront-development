<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Domain;

use Carbon\CarbonImmutable;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\OneOffScripts\NovaOneOffScriptAbstractAction;
use Waterfront\Apps\OneOffScripts\Domain\Services\MissedRtrTransferAwayCancellationService;
use Waterfront\Infra\Common\DateTimeFormat;
use Webmozart\Assert\Assert;

class NovaCancelMissedRtrTransferAwaySubscriptionsAction extends NovaOneOffScriptAbstractAction
{
    public const string SLUG = 'cancel-missed-rtr-transfer-away-subscriptions';

    public function __construct(
        private readonly MissedRtrTransferAwayCancellationService $missedRtrTransferAwayCancellationService,
    ) {
        parent::__construct();
    }

    /** @return array<Field> */
    public function fields(NovaRequest $request): array
    {
        return [
            ...$this->getOneOffScriptInfoFields(),
            Boolean::make('Dry run', 'dry-run')->default(true),
            Date::make('Start date', 'start-date')
                /*
                 * RTR PHP client v1.1.0 removed the transferType from the Notification DTO.
                 * That date is used as the default start date
                */
                ->default('2025-02-14')
                ->max(CarbonImmutable::today()->format(DateTimeFormat::DATE))
                ->rules('required', 'date', 'before_or_equal:today'),
        ];
    }

    public function handle(ActionFields $fields): ActionResponse|static
    {
        $startDateString = $fields->get('start-date');
        Assert::stringNotEmpty($startDateString);

        $startDate = CarbonImmutable::createFromFormat(DateTimeFormat::DATE, $startDateString);
        Assert::isInstanceOf($startDate, CarbonImmutable::class, 'Couldn\'t load the start date from the input form.');
        $startDate = $startDate->startOfDay();

        $dryRun = (bool) $fields->get('dry-run');

        $processed = $this->missedRtrTransferAwayCancellationService->handle(
            startDate: $startDate,
            dryRun: $dryRun,
        );

        if (! $dryRun) {
            $this->registerExecution();
        }

        if ($dryRun) {
            return self::message(sprintf(
                'Dry run found %d missed RTR transfer-away subscription(s).',
                $processed,
            ));
        }

        return self::message(sprintf(
            'Cancelled %d missed RTR transfer-away subscription(s).',
            $processed,
        ));
    }

    protected function getOneOffScriptSlug(): string
    {
        return self::SLUG;
    }

    protected function getOneOffScriptTicketUrl(): string
    {
        return 'https://yh-jira.atlassian.net/browse/SWD-16704';
    }
}
