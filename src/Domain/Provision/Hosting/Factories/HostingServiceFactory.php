<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Hosting\Factories;

use Illuminate\Contracts\Validation\Validator;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Hosting\Exceptions\UnknownHostingProviderException;
use Waterfront\Domain\Provision\Hosting\Exceptions\UnknownHostingRequestException;
use Waterfront\Domain\Provision\Hosting\Interfaces\HostingProvisionServiceInterface;
use Waterfront\Domain\Provision\Hosting\Services\DirectAdminProvisionService;
use Waterfront\Domain\Provision\Hosting\Services\PleskProvisionService;
use Waterfront\Domain\Provision\Hosting\Validators\DirectAdminValidator;
use Waterfront\Domain\Provision\Hosting\Validators\PleskValidator;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionServiceFactoryInterface;

class HostingServiceFactory implements ProvisionServiceFactoryInterface
{
    public function __construct(
        private readonly PleskProvisionService $pleskService,
        private readonly DirectAdminProvisionService $directadminService,
        private readonly DirectAdminValidator $directAdminValidator,
        private readonly PleskValidator $pleskValidator,
    ) {
    }

    /**
     * @throws UnknownHostingProviderException
     * @throws UnknownHostingRequestException
     */
    public function getValidator(ProvisionProvider $provider, ProvisionRequestInterface $provisionRequest): Validator
    {
        return match ($provider) {
            ProvisionProvider::DIRECTADMIN => $this->directAdminValidator->getValidatorByRequest($provisionRequest),
            ProvisionProvider::PLESK => $this->pleskValidator->getValidatorByRequest($provisionRequest),
            default => throw new UnknownHostingProviderException($provider),
        };
    }

    /**
     * @throws UnknownHostingProviderException
     */
    public function getProviderService(ProvisionProvider $provider): HostingProvisionServiceInterface
    {
        return match ($provider) {
            ProvisionProvider::DIRECTADMIN => $this->directadminService,
            ProvisionProvider::PLESK => $this->pleskService,
            default => throw new UnknownHostingProviderException($provider),
        };
    }
}
