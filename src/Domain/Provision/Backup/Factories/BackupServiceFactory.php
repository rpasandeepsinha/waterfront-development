<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Factories;

use Illuminate\Contracts\Validation\Validator;
use Waterfront\Domain\Provision\Backup\Exceptions\UnknownBackupProviderException;
use Waterfront\Domain\Provision\Backup\Exceptions\UnknownBackupRequestException;
use Waterfront\Domain\Provision\Backup\Interfaces\BackupProvisionServiceInterface;
use Waterfront\Domain\Provision\Backup\Services\AcronisProvisionService;
use Waterfront\Domain\Provision\Backup\Validators\AcronisValidator;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionServiceFactoryInterface;

readonly class BackupServiceFactory implements ProvisionServiceFactoryInterface
{
    public function __construct(
        private AcronisValidator $acronisValidator,
        private AcronisProvisionService $acronisProvisionService,
    ) {
    }

    /**
     * @throws UnknownBackupRequestException
     * @throws UnknownBackupProviderException
     */
    public function getValidator(ProvisionProvider $provider, ProvisionRequestInterface $provisionRequest): Validator
    {
        return match ($provider) {
            ProvisionProvider::ACRONIS => $this->acronisValidator->getValidatorByRequest($provisionRequest),
            default => throw new UnknownBackupProviderException($provider),
        };
    }

    /**
     * @throws UnknownBackupProviderException
     */
    public function getProviderService(ProvisionProvider $provider): BackupProvisionServiceInterface
    {
        return match ($provider) {
            ProvisionProvider::ACRONIS => $this->acronisProvisionService,
            default => throw new UnknownBackupProviderException($provider),
        };
    }
}
