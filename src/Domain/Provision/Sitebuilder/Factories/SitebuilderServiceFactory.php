<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Factories;

use Illuminate\Contracts\Validation\Validator;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionServiceFactoryInterface;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\UnknownSitebuilderProviderException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\UnknownSitebuilderRequestException;
use Waterfront\Domain\Provision\Sitebuilder\Interfaces\SitebuilderProvisionServiceInterface;
use Waterfront\Domain\Provision\Sitebuilder\Services\BasekitProvisionService;
use Waterfront\Domain\Provision\Sitebuilder\Validators\BasekitValidator;

class SitebuilderServiceFactory implements ProvisionServiceFactoryInterface
{
    public function __construct(
        private readonly BasekitValidator $basekitValidator,
        private readonly BasekitProvisionService $basekitProvisionService,
    ) {
    }

    /**
     * @throws UnknownSitebuilderRequestException
     * @throws UnknownSitebuilderProviderException
     */
    public function getValidator(ProvisionProvider $provider, ProvisionRequestInterface $provisionRequest): Validator
    {
        return match ($provider) {
            ProvisionProvider::BASEKIT => $this->basekitValidator->getValidatorByRequest($provisionRequest),
            default => throw new UnknownSitebuilderProviderException($provider),
        };
    }

    /**
     * @throws UnknownSitebuilderProviderException
     */
    public function getProviderService(ProvisionProvider $provider): SitebuilderProvisionServiceInterface
    {
        return match ($provider) {
            ProvisionProvider::BASEKIT => $this->basekitProvisionService,
            default => throw new UnknownSitebuilderProviderException($provider),
        };
    }
}
