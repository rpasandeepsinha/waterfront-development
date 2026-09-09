<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Microsoft365\Factories;

use Illuminate\Contracts\Validation\Validator;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionServiceFactoryInterface;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\UnknownMicrosoft365ProviderException;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\UnknownMicrosoft365RequestException;
use Waterfront\Domain\Provision\Microsoft365\Interfaces\Microsoft365ProvisionServiceInterface;
use Waterfront\Domain\Provision\Microsoft365\Services\MicrosoftOnlineService;
use Waterfront\Domain\Provision\Microsoft365\Validators\MicrosoftOnlineValidator;

class Microsoft365ServiceFactory implements ProvisionServiceFactoryInterface
{
    public function __construct(
        private readonly MicrosoftOnlineValidator $microsoftOnlineValidator,
        private readonly MicrosoftOnlineService $microsoftOnlineService
    ) {
    }

    /**
     * @throws UnknownMicrosoft365ProviderException
     * @throws UnknownMicrosoft365RequestException
     */
    public function getValidator(ProvisionProvider $provider, ProvisionRequestInterface $provisionRequest): Validator
    {
        return match ($provider) {
            ProvisionProvider::MICROSOFT_ONLINE => $this->microsoftOnlineValidator->getValidatorByRequest($provisionRequest),
            default => throw new UnknownMicrosoft365ProviderException($provider)
        };
    }

    /**
     * @throws UnknownMicrosoft365ProviderException
     */
    public function getProviderService(ProvisionProvider $provider): Microsoft365ProvisionServiceInterface
    {
        return match ($provider) {
            ProvisionProvider::MICROSOFT_ONLINE => $this->microsoftOnlineService,
            default => throw new UnknownMicrosoft365ProviderException($provider)
        };
    }
}
