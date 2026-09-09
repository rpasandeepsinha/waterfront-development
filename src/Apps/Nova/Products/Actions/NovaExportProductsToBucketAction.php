<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Products\Actions;

use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Waterfront\Domain\Products\ProductListUpdater;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaExportProductsToBucketAction extends Action
{
    public $onlyOnIndex = true;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ProductListUpdater $productListUpdater,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.export_products_to_object_storage');
    }

    public function handle(): ActionResponse|static
    {
        $this->productListUpdater->update();

        return Action::message($this->translator->translate('nova-action.export_products_to_object_storage_success'));
    }
}
