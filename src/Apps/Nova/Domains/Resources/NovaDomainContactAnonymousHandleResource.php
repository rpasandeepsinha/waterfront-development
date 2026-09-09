<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Domains\Resources;

use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Domains\Actions\NovaExportAnonymousDomainContactDomains;
use Waterfront\Apps\Nova\Domains\Actions\NovaFetchAnonymousDomainContactFromRtr;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Domain\Domains\Models\DomainContactAnonymousHandle;

/**
 * @property DomainContactAnonymousHandle $resource
 */
class NovaDomainContactAnonymousHandleResource extends Resource
{
    public static string $model = DomainContactAnonymousHandle::class;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $search = [
        'handle',
        'original_business_unit',
    ];

    public static function getTranslationKey(): string
    {
        return 'domain-contact-anonymous-handles';
    }

    /** @return array<int, Field> */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('handle')
                ->required()
                ->rules('required', 'unique:domain_contact_anonymous_handles,handle')
                ->help(self::translate('domain-contact-anonymous-handles.handle_help'))
                ->sortable(),
            Text::make('original_business_unit')
                ->required()
                ->rules('required')
                ->sortable(),
        ];
    }

    /** @return array<int, Action> */
    public function actions(NovaRequest $request): array
    {
        return [
            resolve(NovaExportAnonymousDomainContactDomains::class),
            resolve(NovaFetchAnonymousDomainContactFromRtr::class),
        ];
    }
}
