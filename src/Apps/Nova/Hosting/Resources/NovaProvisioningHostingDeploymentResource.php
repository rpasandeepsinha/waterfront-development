<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasOne;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\ViewOnlyResourceTrait;
use Waterfront\Apps\Nova\Provision\Resources\NovaProvisionRequestResource;
use Waterfront\Domain\Provision\Hosting\Models\DirectAdminHostingDeployment;
use Waterfront\Domain\Provision\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Provision\Hosting\Models\PleskHostingDeployment;

/** @property HostingDeployment $resource */
class NovaProvisioningHostingDeploymentResource extends Resource
{
    use ViewOnlyResourceTrait;

    public static string $model = HostingDeployment::class;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $with = ['pleskDeployment', 'directAdminDeployment'];

    /** @var array<mixed> */
    public static $search = ['id', 'domain'];

    public static function getTranslationKey(): string
    {
        return 'hosting-deployment';
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->onlyOnDetail(),
            Text::make(self::translate('subscription.attributes.domain'), 'domain')->copyable(),
            BelongsTo::make(self::translate('server.singular'), 'server', NovaServerResource::class),
            HasOne::make(
                self::translate('provisioning-request.singular'),
                'request',
                NovaProvisionRequestResource::class,
            )->onlyOnDetail(),
            Text::make(self::translate('plesk-hosting.username'), 'pleskDeployment')
                ->onlyOnDetail()
                ->copyable()
                ->canSee(fn (Request $request) => $this->resource->pleskDeployment()->exists())
                // @phpstan-ignore-next-line argument.type Comes from Laravel Nova
                ->displayUsing(fn (PleskHostingDeployment $pleskHosting) => $pleskHosting->customer_name),
            Text::make(self::translate('directadmin-hosting.username'), 'directAdminDeployment')
                ->onlyOnDetail()
                ->copyable()
                ->canSee(fn (Request $request) => $this->resource->directAdminDeployment()->exists())
                // @phpstan-ignore-next-line argument.type Comes from Laravel Nova
                ->displayUsing(fn (DirectAdminHostingDeployment $daHosting) => $daHosting->username),
            Text::make('UUID', 'uuid')->copyable(),
        ];
    }
}
