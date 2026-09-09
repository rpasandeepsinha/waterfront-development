<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Acronis\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasOne;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\ViewOnlyResourceTrait;
use Waterfront\Apps\Nova\Provision\Resources\NovaProvisionRequestResource;
use Waterfront\Domain\Provision\Backup\Models\BackupDeployment;

/** @property BackupDeployment $resource */
class NovaBackupDeploymentResource extends Resource
{
    use ViewOnlyResourceTrait;

    public static string $model = BackupDeployment::class;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $with = ['acronisBackupDeployment', 'acronisProvider'];

    /** @var array<mixed> */
    public static $search = ['id', 'uuid'];

    public static function getTranslationKey(): string
    {
        return 'backup-deployment';
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->onlyOnDetail(),

            HasOne::make(self::translate('provisioning-request.singular'), 'request', NovaProvisionRequestResource::class)
                ->onlyOnDetail(),

            Text::make(
                self::translate('acronis-backup-deployment.tenant_uuid'),
                fn (): ?string =>
                $this->resource->acronisBackupDeployment?->tenant_uuid?->toString()
            )
                ->onlyOnDetail()
                ->copyable()
                ->canSee(fn (Request $request): bool => $this->resource->acronisBackupDeployment !== null),

            Text::make(
                self::translate('acronis-backup-deployment.user_uuid'),
                fn (): ?string =>
                $this->resource->acronisBackupDeployment?->user_uuid?->toString()
            )
                ->onlyOnDetail()
                ->copyable()
                ->canSee(fn (Request $request): bool => $this->resource->acronisBackupDeployment !== null),

            Text::make('UUID', 'uuid')->copyable(),
        ];
    }
}
