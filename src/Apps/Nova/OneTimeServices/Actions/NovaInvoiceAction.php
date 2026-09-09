<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\OneTimeServices\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceInvoiceService;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaInvoiceAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly OneTimeServiceInvoiceService $action,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.one-time-service.invoice');
    }

    /**
     * @param Collection<int, OneTimeService> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $this->action->createFromCollection($models);

        return self::message(
            $this->translator->translate('nova-action.one-time-service.invoice.success'),
        );
    }
}
