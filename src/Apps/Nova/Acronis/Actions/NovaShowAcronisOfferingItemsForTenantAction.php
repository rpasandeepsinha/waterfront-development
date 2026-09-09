<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Acronis\Actions;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Waterfront\Domain\Backup\Services\BackupService;
use Waterfront\Domain\Provision\Backup\Acronis\Models\AcronisProvider;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Infra\AcronisClient\Serializers\AcronisSerializer;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class NovaShowAcronisOfferingItemsForTenantAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly BackupService $backupService,
    ) {
        $this->sole();
        $this->showOnDetail();
        $this->showOnIndex();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.acronis-provider.offering-items.action_name');
    }

    /**
     * @return array<int, Text>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make(
                $this->translator->translate('nova-action.acronis-provider.offering-items.tenant_uuid'),
                'tenant_uuid'
            )->rules('required', 'uuid'),
        ];
    }

    /**
     * @param Collection<int, AcronisProvider> $models
     *
     * @throws ExceptionInterface
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $provider = $models->firstOrFail();
        $tenantUuid = (string) $fields->string('tenant_uuid');

        try {
            $offeringItems = $this->backupService->getOfferingItemsForProviderByTenant($provider, $tenantUuid);
        } catch (Exception $exception) { // @phpstan-ignore-line Broad catch is valid for displaying API failures.
            $this->logger->error(
                sprintf(
                    'Error fetching Acronis offering items for tenant [%s] via provider [%d (%s)]: %s',
                    $tenantUuid,
                    $provider->id,
                    $provider->name,
                    $exception->getMessage()
                ),
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::ACRONIS,
                ]
            );
            return self::danger($this->translator->translate('nova-action.acronis-provider.offering-items.failure') . ' => ' . $exception::class);
        }

        return self::modal('modal-response', [
            'title' => $this->translator->translate('nova-action.acronis-provider.offering-items.success'),
            'code' => json_encode(AcronisSerializer::get()->normalize($offeringItems), JSON_PRETTY_PRINT),
            'size' => '7xl',
        ]);
    }
}
