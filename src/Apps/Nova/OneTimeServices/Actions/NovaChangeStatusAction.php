<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\OneTimeServices\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceUpdater;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class NovaChangeStatusAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly OneTimeServiceUpdater $oneTimeServiceUpdater,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.one-time-service.change-status');
    }

    /**
     * @param Collection<int, OneTimeService> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $statusString = $fields->get('status');
        Assert::stringNotEmpty(
            $statusString,
            $this->translator->translate('nova-action.one-time-service.change-status.error.empty'),
        );
        $status = OneTimeServiceStatus::from($statusString);

        $oneTimeServices = OneTimeService::query()->whereIn('id', array_map(
            fn (OneTimeService $service): int => $service->id,
            $models->all(),
        ));

        $oneTimeServices->each(
            fn (OneTimeService $service) => $this->oneTimeServiceUpdater->updateStatus($service, $status),
        );

        return self::message(
            $this->translator->translate('nova-action.one-time-service.change-status.success'),
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Select::make(
                $this->translator->translate('nova-resource-labels.one-time-service.field.status'),
                'status',
            )->options([
                OneTimeServiceStatus::OPEN->value => $this->translator->translate(
                    'one-time-service.status.' . strtolower(OneTimeServiceStatus::OPEN->name),
                ),
                OneTimeServiceStatus::IN_PROGRESS->value => $this->translator->translate(
                    'one-time-service.status.' . strtolower(OneTimeServiceStatus::IN_PROGRESS->name),
                ),
                OneTimeServiceStatus::DONE->value => $this->translator->translate(
                    'one-time-service.status.' . strtolower(OneTimeServiceStatus::DONE->name),
                ),
            ]),
        ];
    }
}
