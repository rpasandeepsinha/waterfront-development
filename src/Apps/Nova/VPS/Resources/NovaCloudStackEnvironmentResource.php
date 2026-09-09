<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\VPS\Resources;

use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Fields\Credential;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\VPS\Rules\Uppercase;
use Waterfront\Domain\VPS\Models\Environment;

/** @property Environment $resource */
class NovaCloudStackEnvironmentResource extends Resource
{
    public static string $model = Environment::class;

    public static $globallySearchable = false;

    public static function getTranslationKey(): string
    {
        return 'cloudstack-environments';
    }

    public function title(): string
    {
        return $this->resource->name;
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.cloudstack_environments');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make(self::translate('cloudstack-environments.attributes.slug'), 'slug')
                ->help(self::translate('cloudstack-environments.attributes_help.slug'))
                ->showOnUpdating(false)
                ->rules('required', 'alpha_num', resolve(Uppercase::class)),
            Text::make(self::translate('cloudstack-environments.attributes.name'), 'name')
                ->sortable()
                ->required(),
            Text::make(self::translate('cloudstack-environments.attributes.api_url'), 'api_url')
                ->sortable()
                ->rules('required', 'url'),
            Text::make(self::translate('cloudstack-environments.attributes.ui_url'), 'ui_url')
                ->hideFromIndex()
                ->rules('required', 'url'),
            Text::make(self::translate('cloudstack-environments.attributes.domain_id'), 'domain_id')
                ->help(self::translate('cloudstack-environments.attributes_help.domain_id'))
                ->hideFromIndex()
                ->rules('required', 'uuid'),
            Text::make(self::translate('cloudstack-environments.attributes.domain_name'), 'domain_name')
                ->sortable()
                ->required()
                ->help(self::translate('cloudstack-environments.attributes_help.domain_name')),
            NovaBoolField::make(self::translate('cloudstack-environments.attributes.preferred'), 'preferred')
                ->help(self::translate('cloudstack-environments.attributes_help.preferred')),
            Text::make(self::translate('cloudstack-environments.attributes.default_email_address'), 'default_email_address')
                ->help(self::translate('cloudstack-environments.attributes_help.default_email_address'))
                ->hideFromIndex()
                ->rules('required', 'email'),
            Text::make(self::translate('cloudstack-environments.attributes.default_role_id'), 'default_role_id')
                ->help(self::translate('cloudstack-environments.attributes_help.default_role_id'))
                ->hideFromIndex()
                ->rules('required', 'uuid'),
            Credential::make('SECRET KEY', 'secret_key')
                ->onlyOnForms()
                ->creationRules('required')
                ->updateRules('nullable'),
            Credential::make('API KEY', 'api_key')
                ->onlyOnForms()
                ->creationRules('required')
                ->updateRules('nullable'),
        ];
    }
}
