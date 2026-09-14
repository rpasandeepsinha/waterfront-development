<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\UnauthorizedException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Waterfront\Policies\DomainDeploymentPolicy;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Apps\API\Waterfront\Requests\DomainName\DecoupleRequest;
use Waterfront\Apps\API\Waterfront\Requests\DomainName\DomainNameCoupleRequest;
use Waterfront\Apps\API\Waterfront\Requests\DomainName\EnableDnssecRequest;
use Waterfront\Apps\API\Waterfront\Requests\DomainName\RetryPovisioningRequest;
use Waterfront\Apps\API\Waterfront\Resources\DomainDeploymentResource;
use Waterfront\Domain\DNS\Events\CreateDns;
use Waterfront\Domain\Domains\Actions\DomainNameCoupleAction;
use Waterfront\Domain\Domains\Actions\DomainNameDecoupleAction;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Events\CreateDomain;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Orders\Serializers\CartSerializerFactory;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\DomainNameCoupleActionException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\DomainNameDecoupleActionException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\SubscriptionProcessor\Builder\EventSubscriptionDataBuilder;
use Waterfront\Infra\OpenproviderClient\Exceptions\OpenProviderResultException;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKey;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;
use Webmozart\Assert\Assert;

class DomainNameController
{
    public function __construct(
        private readonly SubscriptionPolicy $subscriptionPolicy,
        private readonly TranslatorInterface $translator,
        private readonly Dispatcher $eventDispatcher,
        private readonly EventSubscriptionDataBuilder $builder,
        private readonly CartSerializerFactory $cartSerializerFactory,
        private readonly DomainService $domainService,
        private readonly DomainDeploymentRepository $domainDeploymentRepository,
        private readonly LoggerInterface $logger,
        private readonly DomainDeploymentResource $domainDeploymentResource,
        private readonly DomainDeploymentPolicy $deploymentPolicy,
        private readonly DomainNameCoupleAction $domainNameCoupleAction,
        private readonly DomainNameDecoupleAction $domainNameDecoupleAction,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     *
     * @return array <mixed>
     */
    public function getDeployment(DomainDeployment $domainDeployment): array
    {
        $domainDeployment->loadMissing('subscription');
        $this->subscriptionPolicy->assertCanManageDomain($domainDeployment->subscription);

        return $this->domainDeploymentResource->toArray($domainDeployment);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function indexDnssec(string $domain): JsonResponse
    {
        $domainDeployment = $this->domainDeploymentRepository->getDomainDeploymentByDomain($domain);
        if ($domainDeployment === null) {
            throw new UnauthorizedException();
        }

        $this->subscriptionPolicy->assertCanView($domainDeployment->subscription);

        try {
            $keys = $this->domainService->retrieveDnssecKeys($domain, $domainDeployment->provider->slug);
        } catch (NotImplementedException) {
            // We will return an empty success data, the frontend will not show this if
            // a placeholder provider is being used, it will show the migration warning
            return new JsonResponse([
                'data' => [],
            ]);
        } catch (OpenProviderResultException $exception) {
            if ($exception->getCode() !== 320) {
                throw $exception;
            }

            return new JsonResponse(
                ['message' => 'Domain not found at registry'],
                Response::HTTP_NOT_FOUND,
            );
        }

        return new JsonResponse(['data' => $keys]);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function enableDnssec(EnableDnssecRequest $request, string $domain): JsonResponse
    {
        $domainDeployment = $this->domainDeploymentRepository->getDomainDeploymentByDomain($domain);
        if ($domainDeployment === null) {
            throw new UnauthorizedException();
        }

        $this->deploymentPolicy->assertCanUseDnsSec($domainDeployment);

        $this->subscriptionPolicy->assertCanManageDomain($domainDeployment->subscription);

        $request->all();
        $keyInfo = $request->validated();
        $key = null;

        /**
         * In principle, the request parameters are not mandatory. Unless in combination with each other.
         * It is therefore sufficient to check whether the key generation should be performed by checking
         * if the array is not empty.
         *
         */
        if (count($keyInfo) !== 0) {
            // Only dnskey is used in ModifyParameters
            $key = PowerDnsSecKey::fromArray([
                'dnskey' => sprintf(
                    '%d 3 %d %s',
                    $request->integer('flags'),
                    $request->integer('alg'),
                    $request->string('pubKey'),
                ),
            ]);
        }

        $enabled = $this->domainService->enableDnssec($domain, $domainDeployment->provider->slug, $key);

        if ($enabled) {
            $dnssecKeys = $this->domainService->retrieveDnssecKeys($domain, $domainDeployment->provider->slug);

            return new JsonResponse(['data' => $dnssecKeys]);
        }

        return new JsonResponse(
            ['message' => 'Could not enable DNSSEC'],
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function disableDnssec(string $domain): JsonResponse
    {
        $domainDeployment = $this->domainDeploymentRepository->getDomainDeploymentByDomain($domain);
        if ($domainDeployment === null) {
            throw new UnauthorizedException();
        }

        $this->subscriptionPolicy->assertCanManageDomain($domainDeployment->subscription);

        $disabled = $this->domainService->disableDnssec($domain, $domainDeployment->provider->slug);

        if (! $disabled) {
            return new JsonResponse(
                ['message' => 'Could not disable DNSSEC'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return new JsonResponse(['message' => $this->translator->translate('status.success')]);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function transferCode(DomainDeployment $domainDeployment): JsonResponse
    {
        $domainDeployment->loadMissing('subscription');
        $this->subscriptionPolicy->assertCanManageDomain($domainDeployment->subscription);
        Assert::string($domainDeployment->subscription->domain);

        $this->domainService->modify(
            $domainDeployment->subscription->domain,
            ['isLocked' => false],
            $domainDeployment->provider->slug,
        );

        try {
            $authCode = $this->domainService->retrieveAuthCode(
                $domainDeployment->provider->slug,
                $domainDeployment->subscription->domain,
            );
        } catch (Exception $exception) {
            Log::error(sprintf(
                'Error retrieving domain transfercode for: [%s], Could not retrieve authcode from provider. Message: %s',
                $domainDeployment->subscription->domain,
                $exception->getMessage(),
            ));

            return new JsonResponse([
                'message' => sprintf(
                    'Could not retrieve transfer code from domain provider for domain [%s]',
                    $domainDeployment->subscription->domain,
                ),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'data' => [
                'transfer_code' => $authCode,
            ],
        ]);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function manualDnssecAvailable(string $domain): JsonResponse
    {
        $domainDeployment = $this->domainDeploymentRepository->getDomainDeploymentByDomain($domain);
        if ($domainDeployment === null) {
            throw new UnauthorizedException();
        }

        $this->subscriptionPolicy->assertCanManageDomain($domainDeployment->subscription);
        $available = false;

        try {
            $available = $this->domainService->manualDnssecAvailable($domain, $domainDeployment);
        } catch (OpenProviderResultException $exception) {
            if ($exception->getCode() !== 320) {
                throw $exception;
            }
        }

        return new JsonResponse(['data' => $available]);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function retryProvisioning(RetryPovisioningRequest $request, string $domain): JsonResponse
    {
        $domainDeployment = $this->domainDeploymentRepository->getDomainDeploymentByDomain($domain);
        if ($domainDeployment === null) {
            throw new UnauthorizedException();
        }

        $this->subscriptionPolicy->assertCanRetryProvisioning($domainDeployment->subscription);
        $subscription = $domainDeployment->subscription;

        $transferCode = $request->has('transferCode') ? $request->transferCode : null;
        $metaData = $this->builder->buildExtensionMetaData($subscription->orderLineItem?->meta_data);

        if ($metaData !== null) {
            $metaData->transferSecret = $transferCode;
            $metaData->contactId = $request->contactId;

            if ($subscription->orderLineItem !== null) {
                $subscription->orderLineItem->meta_data = $this->cartSerializerFactory->get()->encode(
                    $metaData,
                    'json',
                );
            }
        }

        $domainDeployment->transfer_secret = $transferCode;
        $domainDeployment->save();

        $dnsSubscription = $this->domainDeploymentRepository->getDnsChildSubscription($subscription);
        if ($dnsSubscription === null) {
            $this->logger->notice(
                'Retry Provisioning - Failed because DNS subscription could not be found for domain [{domain.name}]',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                ],
            );

            return new JsonResponse([
                'message' => sprintf(
                    'Could not retrieve DNS from domain [%s]',
                    $domain,
                ),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        Assert::stringNotEmpty($subscription->domain);

        $this->eventDispatcher->dispatch(
            new CreateDns(
                $dnsSubscription->uuid,
                $subscription->domain,
            ),
        );

        $this->eventDispatcher->dispatch(
            new CreateDomain(
                $domain,
                $subscription,
                $domainDeployment,
            ),
        );

        return new JsonResponse([
            'message' => $this->translator->translate('technical_subscription.provisioning.initiated_retry_success'),
        ]);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function couple(DomainNameCoupleRequest $request, Subscription $subscription): JsonResponse
    {
        $this->subscriptionPolicy->assertCanManageDomain($subscription);

        $coupleSubscriptionUuid = $request->input('uuid');
        Assert::string($coupleSubscriptionUuid);
        $coupleSubscription = $this->subscriptionRepository->getByUuid($coupleSubscriptionUuid);
        Assert::isInstanceOf($coupleSubscription, Subscription::class);

        try {
            Assert::string($subscription->domain);
            $this->domainNameCoupleAction->execute($subscription->domain, $coupleSubscription);
        } catch (DomainNameCoupleActionException) {
            return new JsonResponse([
                'message' => $this->translator->translate('domain-name.couple-failed'),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['message' => $this->translator->translate('status.success')]);
    }

    public function decouple(DecoupleRequest $request, Subscription $subscription): JsonResponse
    {
        $this->subscriptionPolicy->assertCanManageDomain($subscription);

        $domain = $subscription->domain;
        Assert::string($domain);

        $uuid = $request->input('uuid');
        Assert::string($uuid);

        $decoupleSubscription = $this->subscriptionRepository->getByUuid($uuid);
        Assert::isInstanceOf($decoupleSubscription, Subscription::class);

        try {
            $this->domainNameDecoupleAction->execute($domain, $decoupleSubscription);

            return new JsonResponse([
                'message' => $this->translator->translate('status.success'),
            ]);
        } catch (DomainNameDecoupleActionException) {
            return new JsonResponse([
                'message' => $this->translator->translate('domain-name.decouple-failed'),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
