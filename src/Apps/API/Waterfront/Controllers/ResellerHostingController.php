<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Waterfront\Policies\ProductPolicy;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Apps\API\Waterfront\Requests\ResellerHosting\CoupleDomainRequest;
use Waterfront\Apps\API\Waterfront\Requests\ResellerHosting\ShowRequest;
use Waterfront\Apps\API\Waterfront\Resources\ResellerHostingDeploymentResource;
use Waterfront\Apps\API\Waterfront\Resources\ResellerHostingResource;
use Waterfront\Domain\Hosting\Actions\GetSsoUrlAction;
use Waterfront\Domain\Hosting\Exceptions\SsoResolveException;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\ResellerHosting\Exceptions\ResellerHostingException;
use Waterfront\Domain\ResellerHosting\Exceptions\ResellerHostingNameserverCoupleException;
use Waterfront\Domain\ResellerHosting\Exceptions\ResellerHostingSslCoupleException;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\ResellerHosting\Parameters\AppResellerHostingDomainCoupleParameters;
use Waterfront\Domain\ResellerHosting\Services\ResellerHostingService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Exceptions\NotImplementedException;

class ResellerHostingController
{
    public function __construct(
        private readonly ResellerHostingService $resellerHostingService,
        private readonly AuthenticationManager $authenticationManager,
        private readonly GetSsoUrlAction $getSsoUrlAction,
        private readonly SubscriptionPolicy $subscriptionPolicy,
        private readonly TranslatorInterface $translator,
        private readonly ProductPolicy $productPolicy,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly ResellerHostingDeploymentResource $deploymentResource,
    ) {
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     *
     * @return array<mixed>
     *
     */
    public function getDeployment(ResellerHostingDeployment $resellerHostingDeployment): array
    {
        $resellerHostingDeployment->loadMissing('subscription');
        $this->subscriptionPolicy->assertCanManageResellerHosting($resellerHostingDeployment->subscription->uuid);

        return $this->deploymentResource->toArray($resellerHostingDeployment);
    }

    /**
     * @return AnonymousResourceCollection<array>|JsonResponse
     */
    public function index(): AnonymousResourceCollection|JsonResponse
    {
        try {
            $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
            $result = $this->resellerHostingService->getCustomerPackages($customer);

            return ResellerHostingResource::collection($result);
        } catch (AuthenticationException) {
            return new JsonResponse([
                'message' => $this->translator->translate('resellerhosting.unable-to-resolve-customer'),
            ], Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function show(
        ShowRequest $request,
        ResellerHostingDeployment $resellerHostingDeployment,
    ): ResellerHostingResource|JsonResponse {
        $this->subscriptionPolicy->assertCanManageResellerHosting($request->resellerHostingDeployment->subscription->uuid);

        try {
            $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
            $result = $this->resellerHostingService->getCustomerPackage(
                $customer,
                $resellerHostingDeployment->subscription->uuid,
            );

            return ResellerHostingResource::make($result);
        } catch (ResellerHostingException) {
            return new JsonResponse([
                'message' => $this->translator->translate('resellerhosting.package-not-found'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (AuthenticationException) {
            return new JsonResponse([
                'message' => $this->translator->translate('resellerhosting.unable-to-resolve-customer'),
            ], Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function getSsoUrl(
        ShowRequest $request,
        ResellerHostingDeployment $resellerHostingDeployment,
    ): JsonResponse {
        $this->subscriptionPolicy->assertCanManageResellerHosting($request->resellerHostingDeployment->subscription->uuid);

        try {
            $url = $this->getSsoUrlAction->execute(
                $resellerHostingDeployment->server,
                $resellerHostingDeployment->getRelevantUsernameAttribute(),
                $request->ip() ?? '',
            );
        } catch (SsoResolveException|ResellerHostingException|NotImplementedException $exception) {
            Log::error(sprintf(
                "getSsoUrl(): Failed to generate Sso Url: '%s'",
                $exception->getMessage(),
            ));

            return new JsonResponse([
                'message' => $this->translator->translate('resellerhosting.sso-error'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'url' => $url,
        ]);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function resetPassword(
        ShowRequest $request,
        ResellerHostingDeployment $resellerHostingDeployment,
    ): JsonResponse {
        $this->subscriptionPolicy->assertCanManageResellerHosting($request->resellerHostingDeployment->subscription->uuid);

        try {
            $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
            $result = $this->resellerHostingService->resetPassword($customer, $resellerHostingDeployment);

            return new JsonResponse($result);
        } catch (ResellerHostingException $exception) {
            Log::error(
                self::class . ':: resetPassword' . ', message: ' . $exception->getMessage() . ', trace: '
                    . $exception->getTraceAsString(),
            );

            return new JsonResponse([
                'message' => $this->translator->translate('resellerhosting.reset-password-failed'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (AuthenticationException) {
            return new JsonResponse([
                'message' => $this->translator->translate('resellerhosting.unable-to-resolve-customer'),
            ], Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function coupleExistingDomain(
        CoupleDomainRequest $request,
        ResellerHostingDeployment $resellerHostingDeployment,
    ): JsonResponse {
        $this->subscriptionPolicy->assertCanManageResellerHosting($resellerHostingDeployment->subscription->uuid);

        $parameters = AppResellerHostingDomainCoupleParameters::fromArray($request->all());

        $dnsSubscription = $this->subscriptionRepository->getSubscriptionByCustomerDomainAndType(
            $resellerHostingDeployment->subscription->customer,
            $parameters->getDomain(),
            ProductGroupType::DNS,
        );

        if ($dnsSubscription === null) {
            return new JsonResponse([
                'message' => $this->translator->translate('resellerhosting.couple-domain-no-dns-subscription'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->productPolicy->assertCanCoupleHosting($dnsSubscription);

        try {
            /** @var Subscription|null $domainSubscription */
            $domainSubscription = Subscription::query()
                ->whereProductGroupType(ProductGroupType::EXTENSION)
                ->where('domain', $parameters->getDomain())
                ->first();

            if ($domainSubscription === null) {
                throw ResellerHostingException::noDomainSubscriptionFound($parameters->getDomain());
            }

            $this->resellerHostingService->coupleExistingDomain(
                $resellerHostingDeployment,
                $domainSubscription,
                $parameters,
            );

            return new JsonResponse([
                'message' => $this->translator->translate('resellerhosting.couple-domain-success'),
            ]);
        } catch (ResellerHostingSslCoupleException $exception) {
            Log::error(
                self::class . ':: coupleExistingDomain' . ', message: ' . $exception->getMessage() . ', trace: '
                    . $exception->getTraceAsString(),
            );

            return new JsonResponse([
                'message' => $this->translator->translate('resellerhosting.install-certificate-failed'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ResellerHostingNameserverCoupleException $exception) {
            Log::error(
                self::class . ':: coupleExistingDomain' . ', message: ' . $exception->getMessage() . ', trace: '
                    . $exception->getTraceAsString(),
            );

            return new JsonResponse([
                'message' => $this->translator->translate('resellerhosting.domain-nameserver-failed'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (RuntimeException $exception) {
            Log::error(
                self::class . ':: coupleExistingDomain' . ', message: ' . $exception->getMessage() . ', trace: '
                    . $exception->getTraceAsString(),
            );

            return new JsonResponse([
                'message' => $this->translator->translate('resellerhosting.couple-domain-binding'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ResellerHostingException $exception) {
            Log::error(
                self::class . ':: coupleExistingDomain' . ', message: ' . $exception->getMessage() . ', trace: '
                    . $exception->getTraceAsString(),
            );

            return new JsonResponse([
                'message' => $this->translator->translate('resellerhosting.couple-domain-failed'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (AuthorizationException) {
            return new JsonResponse([
                'message' => $this->translator->translate('resellerhosting.unable-to-resolve-customer'),
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function getResellerSubCustomers(
        ResellerHostingDeployment $resellerHostingDeployment,
    ): JsonResponse {
        try {
            $customers = $this->resellerHostingService->getSubAccounts($resellerHostingDeployment);
        } catch (ResellerHostingException $exception) {
            Log::error(
                sprintf(
                    '%s ::getResellerSubCustomers - status code: %s, message: %s, trace: %s',
                    self::class,
                    $exception->getCode(),
                    $exception->getMessage(),
                    $exception->getTraceAsString(),
                ),
            );

            return new JsonResponse([
                'message' => $this->translator->translate('resellerhosting.customers-error'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'customers' => $customers,
        ]);
    }
}
