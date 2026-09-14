<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\OneTimeServices\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceUpdater;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class NovaChangeExecutionDateAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly OneTimeServiceUpdater $oneTimeServiceUpdater,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.one-time-service.change-execution-date');
    }

    /**
     * @param Collection<int, OneTimeService> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $executionDateString = $fields->get('execution_date');
        Assert::stringNotEmpty(
            $executionDateString,
            $this->translator->translate('nova-action.one-time-service.change-execution-date.error.empty'),
        );
        $executionDate = CarbonImmutable::createFromFormat(DateTimeFormat::DUTCH, $executionDateString);
        Assert::isInstanceOf(
            $executionDate,
            CarbonImmutable::class,
            'Couldn\'t load the execution date from the input form.',
        );

        $oneTimeServices = OneTimeService::query()->whereIn('id', array_map(
            fn (OneTimeService $service): int => $service->id,
            $models->all(),
        ));

        $oneTimeServices->each(
            fn (OneTimeService $service) => $this->oneTimeServiceUpdater->changeExecutionDate($service, $executionDate),
        );

        return self::message(
            $this->translator->translate('nova-action.one-time-service.change-execution-date.success'),
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Date::make(
                $this->translator->translate('nova-resource-labels.one-time-service.field.execution-date'),
                'execution_date',
            ),
        ];
    }
}
