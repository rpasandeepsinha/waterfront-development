<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use RealtimeRegister\Exceptions\RealtimeRegisterClientException;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Waterfront\Policies\ProductPolicy;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Apps\API\Waterfront\Requests\Whois\DisablePrivateRequest;
use Waterfront\Apps\API\Waterfront\Requests\Whois\EnablePrivateRequest;
use Waterfront\Apps\API\Waterfront\Requests\Whois\UpdateRequest;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Exceptions\DomainDoesNotExistException;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\OpenproviderClient\Exceptions\OpenProviderResultException;
use Waterfront\Infra\Translation\TranslatorInterface;

class WhoisController
{
    public function __construct(
        private readonly ProductPolicy $productPolicy,
        private readonly SubscriptionPolicy $subscriptionPolicy,
        private readonly TranslatorInterface $translator,
        private readonly DomainService $domainService,
    ) {
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function index(string $domain): JsonResponse
    {
        $domainDeployment = DomainDeployment::whereHas(
            'subscription',
            fn (Builder $query) => $query->where('domain', $domain),
        )
            ->with('provider')
            ->first();

        if ($domainDeployment === null) {
            throw new AuthorizationException();
        }

        $this->subscriptionPolicy->assertCanView($domainDeployment->subscription);

        try {
            $retrievedDomain = $this->domainService->checkNameservers($domainDeployment);
            $handles = $retrievedDomain->getHandles();

            $ownerHandleInfoArray = [];
            $adminHandleInfoArray = [];
            $isPrivate = false;

            if ($handles !== null) {
                $provider = $domainDeployment->provider->slug;

                $ownerHandleInfoArray = $this->domainService
                    ->retrieveContactHandle($handles->getOwnerHandle(), $provider, $domainDeployment->businessUnit)
                    ->toArray();
                $adminHandle = $handles->getAdminHandle();
                assert(is_string($adminHandle));
                $adminHandleInfoArray = $this->domainService
                    ->retrieveContactHandle($adminHandle, $provider, $domainDeployment->businessUnit)
                    ->toArray();
                $isPrivate = $retrievedDomain->getIsPrivateWhoisEnabled();
            }

            return new JsonResponse([
                'data' => [
                    'owner' => $ownerHandleInfoArray,
                    'admin' => $adminHandleInfoArray,
                    'is_private' => $isPrivate,
                ],
            ]);
        } catch (RealtimeRegisterClientException|OpenProviderResultException|DomainDoesNotExistException $exception) {
            return new JsonResponse([
                'reason' => $exception->getMessage(),
                'customer' => $this->translator->translate('partners.api.whoiscontroller.failed-to-retrieve-result'),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     *
     * @see ProductPolicy::assertCanManageWhois()
     */
    public function update(UpdateRequest $request, DomainService $domainService, string $domain): JsonResponse
    {
        /** @var Subscription|null $subscription */
        $subscription = Subscription::query()
            ->whereProductGroup(ProductGroupType::EXTENSION->value)
            ->where('domain', $domain)
            ->with('domainDeployment', 'provider', 'product.productSpecs')
            ->first();

        if ($subscription === null) {
            throw new AuthorizationException();
        }

        $this->productPolicy->assertCanManageWhois($domain);

        if (
            $subscription->product->productSpecs->where('name', 'domain.allow_whois')->pluck('value')->first() === '0'
        ) {
            return new JsonResponse([
                'reason' => 'Not allowed to update whois for extension.',
                'customer' => $this->translator->translate('partners.api.whoiscontroller.whois-update-not-allowed'),
            ], Response::HTTP_FORBIDDEN);
        }

        $domainDeployment = $subscription->domainDeployment;
        assert($domainDeployment instanceof DomainDeployment);

        return new JsonResponse(
            [
                $this->translator->translate('status.success') => $domainService->modifyHandle(
                    $domain,
                    $request->all(),
                    $domainDeployment->provider->slug,
                ),
            ],
        );
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function enablePrivateWhois(EnablePrivateRequest $request, DomainService $domainService): JsonResponse
    {
        $domain = $request->domain;

        /** @var Subscription|null $subscription */
        $subscription = Subscription::query()
            ->whereProductGroup(ProductGroupType::EXTENSION->value)
            ->where('domain', $domain)
            ->with('domainDeployment')
            ->first();

        if ($subscription === null) {
            throw new AuthorizationException();
        }

        $this->subscriptionPolicy->assertCanManageDomain($subscription);

        $domainDeployment = $subscription->domainDeployment;
        assert($domainDeployment instanceof DomainDeployment);

        $enabled = $domainService->enablePrivateWhois($domainDeployment);

        if (! $enabled) {
            return new JsonResponse(
                ['message' => 'Could not enable private whois'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return new JsonResponse([
            'data' => true,
        ]);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function disablePrivateWhois(DisablePrivateRequest $request, DomainService $domainService): JsonResponse
    {
        $domain = $request->domain;

        /** @var Subscription|null $subscription */
        $subscription = Subscription::query()
            ->whereProductGroup(ProductGroupType::EXTENSION->value)
            ->where('domain', $domain)
            ->with('domainDeployment')
            ->first();

        if ($subscription === null) {
            throw new AuthorizationException();
        }

        $this->subscriptionPolicy->assertCanManageDomain($subscription);

        $domainDeployment = $subscription->domainDeployment;
        assert($domainDeployment instanceof DomainDeployment);

        $disabled = $domainService->disablePrivateWhois($domainDeployment);

        if (! $disabled) {
            return new JsonResponse(
                ['message' => 'Could not disable private whois'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return new JsonResponse([
            'data' => true,
        ]);
    }
}
