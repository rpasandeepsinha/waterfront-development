<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Acronis\Actions;

use Illuminate\Database\Eloquent\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Backup\Services\BackupService;
use Waterfront\Domain\Provision\Backup\Acronis\Models\AcronisProvider;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class NovaTestAcronisUserSsoAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly AuthenticationManager $authenticationManager,
        private readonly BackupService $backupService,
    ) {
        $this->sole()
            ->showOnDetail()
            ->showOnIndex();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.acronis-provider.test-user-sso.action_name');
    }

    /**
     * @return array<int, Text>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make(
                $this->translator->translate('nova-action.acronis-provider.test-user-sso.user_uuid'),
                'user_uuid'
            )->rules('required', 'uuid'),
        ];
    }

    /**
     * @param Collection<int, AcronisProvider> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $provider = $models->firstOrFail();
        $userUuid = (string) $fields->string('user_uuid');

        try {
            $employeeUuid = $this->authenticationManager->getAuthenticatedEmployee()->identitySchema->id->toString();
            $ott = $this->backupService->getSsoForProviderByUuids($provider, $userUuid, $employeeUuid);
        } catch (Throwable $exception) { // @phpstan-ignore-line Broad catch is valid for displaying SSO failures.
            $this->logger->error(
                sprintf(
                    'Error generating Acronis SSO link for user [%s] via provider [%d (%s)]: %s',
                    $userUuid,
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
            return self::danger($this->translator->translate('nova-action.acronis-provider.test-user-sso.failure') . ' => ' . $exception::class);
        }

        $ssoUrl = sprintf(
            '%s/idp/external-login#ott=%s&targetURI=%s',
            $provider->endpoint,
            rawurlencode($ott->ott),
            $provider->sso_target_url,
        );

        return self::openInNewTab($ssoUrl);
    }
}
