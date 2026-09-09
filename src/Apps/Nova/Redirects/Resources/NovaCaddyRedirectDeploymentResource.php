<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Redirects\Resources;

use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\ViewOnlyResourceTrait;
use Waterfront\Domain\Provision\Redirects\Models\CaddyRedirectDeployment;

/** @property CaddyRedirectDeployment $resource */
class NovaCaddyRedirectDeploymentResource extends Resource
{
    use ViewOnlyResourceTrait;

    public static string $model = CaddyRedirectDeployment::class;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $search = ['id', 'server'];

    /** @var array<mixed> */
    public static $with = ['redirectDeployment'];

    public static function getTranslationKey(): string
    {
        return 'caddy-redirect-deployment';
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
            Text::make(self::translate('caddy-redirect-deployment.server'), 'server')
                ->copyable()
                ->readonly(),
            BelongsTo::make(self::translate('redirect-deployment.singular'), 'redirectDeployment', NovaRedirectDeploymentResource::class)
                ->readonly()
                // @phpstan-ignore-next-line argument.type Comes from Laravel Nova
                ->displayUsing(fn (NovaRedirectDeploymentResource $resource) => $resource->source . ' → ' . $resource->destination),
        ];
    }
}
