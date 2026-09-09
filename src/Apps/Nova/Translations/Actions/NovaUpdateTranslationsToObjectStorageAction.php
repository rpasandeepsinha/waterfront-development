<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Translations\Actions;

use Illuminate\Support\Facades\Artisan;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Waterfront\Apps\Console\Commands\Translations\UpdateTranslationsS3;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaUpdateTranslationsToObjectStorageAction extends Action
{
    public $onlyOnIndex = true;

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.export_to_dictionary');
    }

    public function handle(): ActionResponse
    {
        Artisan::call(UpdateTranslationsS3::class);

        return Action::message($this->translator->translate('nova-action.export_to_dictionary_success'));
    }
}
