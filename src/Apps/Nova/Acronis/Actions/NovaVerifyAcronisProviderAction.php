<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Acronis\Actions;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Backup\Services\BackupService;
use Waterfront\Domain\Provision\Backup\Acronis\Models\AcronisProvider;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Infra\AcronisClient\Serializers\AcronisSerializer;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class NovaVerifyAcronisProviderAction extends Action
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
        return $this->translator->translate('nova-action.acronis-provider.verify.action_name');
    }

    /**
     * @param Collection<int, AcronisProvider> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $provider = $models->firstOrFail();

        try {
            $applicationList = $this->backupService->getApplicationListFromProvider($provider);
        } catch (Exception $exception) { // @phpstan-ignore-line Broad catch is valid for testing provider.
            $this->logger->error(
                sprintf(
                    'Error when testing Acronis Provider [%d (%s)]: %s',
                    $provider->id,
                    $provider->name,
                    $exception->getMessage(),
                ),
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::ACRONIS,
                ],
            );

            return self::danger(
                $this->translator->translate('nova-action.acronis-provider.verify.failure')
                . ' => '
                . $exception::class,
            );
        }

        return self::modal('modal-response', [
            'title' => $this->translator->translate('nova-action.acronis-provider.verify.success'),
            'code' => json_encode(AcronisSerializer::get()->normalize($applicationList), JSON_PRETTY_PRINT),
            'size' => '7xl',
        ]);
    }
}
