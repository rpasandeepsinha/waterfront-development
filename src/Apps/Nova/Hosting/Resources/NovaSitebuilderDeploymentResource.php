<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasOne;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\ViewOnlyResourceTrait;
use Waterfront\Apps\Nova\Provision\Resources\NovaProvisionRequestResource;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitContext;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitSitebuilderDeployment;
use Waterfront\Domain\Provision\Sitebuilder\Models\SitebuilderDeployment;

/** @property SitebuilderDeployment $resource */
class NovaSitebuilderDeploymentResource extends Resource
{
    use ViewOnlyResourceTrait;

    public static string $model = SitebuilderDeployment::class;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $with = ['basekitDeployment'];

    /** @var array<mixed> */
    public static $search = ['id', 'uuid'];

    public static function getTranslationKey(): string
    {
        return 'sitebuilder-deployment';
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->onlyOnDetail(),
            HasOne::make(
                self::translate('provisioning-request.singular'),
                'request',
                NovaProvisionRequestResource::class,
            )->onlyOnDetail(),
            Text::make(self::translate('basekit-sitebuilder.site-ref'), 'basekitDeployment')
                ->onlyOnDetail()
                ->copyable()
                ->canSee(fn (Request $request) => $this->resource->basekitDeployment()->exists())
                // @phpstan-ignore-next-line argument.type Comes from Laravel Nova
                ->displayUsing(fn (BasekitSitebuilderDeployment $basekitDeployment) => $basekitDeployment->site_ref),
            Text::make(self::translate('basekit-sitebuilder.user-ref'), 'basekitContext')
                ->onlyOnDetail()
                ->copyable()
                ->canSee(fn (Request $request) => $this->resource->basekitContext()->exists())
                // @phpstan-ignore-next-line argument.type Comes from Laravel Nova
                ->displayUsing(fn (BasekitContext $basekitContext) => $basekitContext->user_ref),
            Text::make('UUID', 'uuid')->copyable(),
        ];
    }
}
