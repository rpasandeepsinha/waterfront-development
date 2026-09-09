<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Redirects\Resources;

use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasOne;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\ViewOnlyResourceTrait;
use Waterfront\Apps\Nova\Provision\Resources\NovaProvisionRequestResource;
use Waterfront\Domain\Provision\Redirects\Models\RedirectDeployment;

/** @property RedirectDeployment $resource */
class NovaRedirectDeploymentResource extends Resource
{
    use ViewOnlyResourceTrait;

    public static string $model = RedirectDeployment::class;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $with = ['caddyRedirectDeployment'];

    /** @var array<mixed> */
    public static $search = ['id', 'source', 'destination', 'type'];

    public static function getTranslationKey(): string
    {
        return 'redirect-deployment';
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()
                ->onlyOnDetail(),
            Text::make('UUID', 'uuid')
                ->copyable()
                ->readonly(),
            HasOne::make(self::translate('provisioning-request.singular'), 'request', NovaProvisionRequestResource::class)
                ->onlyOnDetail()
                ->readonly(),
            Text::make(self::translate('redirect-deployment.source'), 'source')
                ->copyable()
                ->readonly(),
            Text::make(self::translate('redirect-deployment.destination'), 'destination')
                ->copyable()
                ->readonly(),
            Text::make(self::translate('redirect-deployment.type'), 'type')
                ->readonly(),
            HasOne::make(self::translate('caddy-redirect-deployment.singular'), 'caddyRedirectDeployment', NovaCaddyRedirectDeploymentResource::class),
        ];
    }
}
