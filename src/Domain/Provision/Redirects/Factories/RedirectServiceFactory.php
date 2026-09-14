<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Factories;

use Illuminate\Contracts\Validation\Validator;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionServiceFactoryInterface;
use Waterfront\Domain\Provision\Redirects\Exceptions\UnknownRedirectProviderException;
use Waterfront\Domain\Provision\Redirects\Exceptions\UnknownRedirectRequestException;
use Waterfront\Domain\Provision\Redirects\Interfaces\RedirectProvisionServiceInterface;
use Waterfront\Domain\Provision\Redirects\Services\CaddyProvisionService;
use Waterfront\Domain\Provision\Redirects\Validators\CaddyValidator;

readonly class RedirectServiceFactory implements ProvisionServiceFactoryInterface
{
    public function __construct(
        private CaddyValidator $caddyValidator,
        private CaddyProvisionService $caddyProvisionService,
    ) {
    }

    /**
     * @throws UnknownRedirectRequestException
     * @throws UnknownRedirectProviderException
     */
    public function getValidator(ProvisionProvider $provider, ProvisionRequestInterface $provisionRequest): Validator
    {
        return match ($provider) {
            ProvisionProvider::CADDY => $this->caddyValidator->getValidatorByRequest($provisionRequest),
            default => throw new UnknownRedirectProviderException($provider),
        };
    }

    /**
     * @throws UnknownRedirectProviderException
     */
    public function getProviderService(ProvisionProvider $provider): RedirectProvisionServiceInterface
    {
        return match ($provider) {
            ProvisionProvider::CADDY => $this->caddyProvisionService,
            default => throw new UnknownRedirectProviderException($provider),
        };
    }
}
