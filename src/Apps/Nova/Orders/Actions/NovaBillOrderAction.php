<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Orders\Actions;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\Jobs\BillOrderJob;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaBillOrderAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Dispatcher $jobDispatcher,
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.bill_order');
    }

    /**
     * @param Collection<int, Order> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $order = $models->firstOrFail();

        if ($order->is_invoiced) {
            return self::danger($this->translator->translate('nova-action.bill_order.already_billed'));
        }

        if ($order->status !== OrderStatus::PROCESSED) {
            return self::danger($this->translator->translate('nova-action.bill_order.invalid_status'));
        }

        $this->jobDispatcher->dispatch(new BillOrderJob($order));

        return self::message($this->translator->translate('nova-action.success.bill_order'));
    }
}
