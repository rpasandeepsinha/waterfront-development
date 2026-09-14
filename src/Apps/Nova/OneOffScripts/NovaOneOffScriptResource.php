<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\OneOffScripts;

use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\URL;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\ResolvesActionsAndFilters;
use Waterfront\Apps\Nova\General\Traits\ViewOnlyResourceTrait;
use Waterfront\Apps\OneOffScripts\CreateRedirectsFromLegacyDatabase\NovaCreateRedirectsFromLegacyDatabaseAction;
use Waterfront\Apps\OneOffScripts\Domain\NovaCancelMissedRtrTransferAwaySubscriptionsAction;
use Waterfront\Apps\OneOffScripts\Domain\NovaFixFailedDomainSubscriptionsAction;
use Waterfront\Apps\OneOffScripts\FreeRedirectActions\NovaUpgradeFreeRedirectAction;
use Waterfront\Apps\OneOffScripts\Legacy\NovaBulkUpdateDomainHandlePrivacyProtectAction;
use Waterfront\Apps\OneOffScripts\Microsoft365\NovaAddMissingTenantOrderIdAction;
use Waterfront\Apps\OneOffScripts\MigrateProvisionResultData\NovaMigrateProvisionResultDataAction;
use Waterfront\Apps\OneOffScripts\MigrateSitebuilderSubscriptions\NovaMigrateBasekitDeploymentsAction;
use Waterfront\Apps\OneOffScripts\Nova\DownloadFileFromS3BucketAction;
use Waterfront\Apps\OneOffScripts\OneOffScript;
use Waterfront\Apps\OneOffScripts\RemoveDuplicatedNameservers\NovaRemoveDuplicatedNamserversAction;
use Waterfront\Apps\OneOffScripts\RemoveStrayParkingDns\NovaRemoveStrayParkingDnsAction;
use Waterfront\Apps\OneOffScripts\RetroFixDeliverdSsl\NovaRetroFixDeliverdSslAction;
use Waterfront\Apps\OneOffScripts\Ssl\NovaFillSslDeploymentExpireDateAction;

class NovaOneOffScriptResource extends Resource
{
    use ResolvesActionsAndFilters;
    use ViewOnlyResourceTrait;

    public static string $model = OneOffScript::class;

    public static $globallySearchable = false;

    public static function getTranslationKey(): string
    {
        return 'one-off-script';
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->hideFromIndex(),
            Text::make(self::translate('nova-resource-labels.one-off-script.field.slug'), 'slug')->readonly(),
            URL::make(
                self::translate('nova-resource-labels.one-off-script.field.ticket_ref'),
                'ticket_ref',
            )->readonly(),
            Text::make(self::translate('nova-resource-labels.one-off-script.field.output_last_run'), 'output_last_run')
                ->onlyOnDetail()
                ->asHtml()
                ->readonly(),
            DateTime::make(
                self::translate('nova-resource-labels.one-off-script.field.last_executed_at'),
                'last_executed_at',
            )->readonly(),
            DateTime::make(self::translate('nova-resource-labels.created_at'), 'created_at')->readonly(),
        ];
    }

    /**
     * Add one-off action here.
     *
     * @return array<Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            $this->resolveAction(DownloadFileFromS3BucketAction::class),
            $this->resolveAction(NovaUpgradeFreeRedirectAction::class),
            $this->resolveAction(NovaRemoveDuplicatedNamserversAction::class),
            $this->resolveAction(NovaMigrateBasekitDeploymentsAction::class),
            $this->resolveAction(NovaFillSslDeploymentExpireDateAction::class),
            $this->resolveAction(NovaFixFailedDomainSubscriptionsAction::class),
            $this->resolveAction(NovaCancelMissedRtrTransferAwaySubscriptionsAction::class),
            $this->resolveAction(NovaCreateRedirectsFromLegacyDatabaseAction::class),
            $this->resolveAction(NovaBulkUpdateDomainHandlePrivacyProtectAction::class),
            $this->resolveAction(NovaRetroFixDeliverdSslAction::class),
            $this->resolveAction(NovaAddMissingTenantOrderIdAction::class),
            $this->resolveAction(NovaMigrateProvisionResultDataAction::class),
            $this->resolveAction(NovaRemoveStrayParkingDnsAction::class),
        ];
    }
}
