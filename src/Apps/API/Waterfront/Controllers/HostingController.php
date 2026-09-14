<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\UnauthorizedException;
use JsonException;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Apps\API\Waterfront\Policies\ProductPolicy;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Apps\API\Waterfront\Requests\Hosting\AddDomainRequest;
use Waterfront\Apps\API\Waterfront\Requests\Hosting\FindDomainHostingCoupleRequest;
use Waterfront\Apps\API\Waterfront\Requests\Hosting\GenerateHostingSsoUrlRequest;
use Waterfront\Apps\API\Waterfront\Requests\Hosting\ToggleDkimRequest;
use Waterfront\Apps\API\Waterfront\Requests\Hosting\UpdateRequest;
use Waterfront\Apps\API\Waterfront\Resources\HostingDeploymentResource;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Hosting\DirectAdmin\Exceptions\CoupleHostingException;
use Waterfront\Domain\Hosting\Exceptions\HostingException;
use Waterfront\Domain\Hosting\Exceptions\SsoResolveException;
use Waterfront\Domain\Hosting\Exceptions\UnableToConvertHostingModelException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Hosting\WpToolkit\WpToolkitService;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\QueryBuilders\SubscriptionQueryBuilder;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;
use Webmozart\Assert\Assert;

class HostingController
{
    private const int TTL = 3600;

    public function __construct(
        private readonly HostingService $hostingService,
        private readonly SubscriptionPolicy $subscriptionPolicy,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
        private readonly HostingDeploymentResource $hostingDeploymentResource,
        private readonly ProductPolicy $productPolicy,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly WpToolkitService $wpToolkitService,
        private readonly DomainService $domainService,
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
        private readonly DnsService $dnsService,
    ) {
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws UnableToConvertHostingModelException
     *
     * @return array<mixed>
     */
    public function getDeployment(HostingDeployment $hostingDeployment): array
    {
        $hostingDeployment->loadMissing('subscription');
        $this->subscriptionPolicy->assertCanManageHosting($hostingDeployment->subscription);

        return $this->hostingDeploymentResource->toArray($hostingDeployment);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function getDomainOccupation(HostingDeployment $hostingDeployment): JsonResponse
    {
        $this->subscriptionPolicy->assertCanManageHosting($hostingDeployment->subscription);

        try {
            $hostingDomainOccupation = $this->hostingService->getDomainOccupation($hostingDeployment);
        } catch (HostingException $exception) {
            $provider = $hostingDeployment->provider?->slug->value ?? 'Unknown';
            $this->logger->error(
                sprintf('Could not retrieve domain slots from %s provider for customer {customer.id}', $provider),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $hostingDeployment->subscription->uuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                    LoggingContextKeys::PROVISIONING_PROVIDER => $provider,
                    LoggingContextKeys::PROVISIONING_ID => $hostingDeployment->id,
                ],
            );

            return new JsonResponse([
                'message' => $this->translator->translate('hosting.domain-slots-failed'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse($hostingDomainOccupation);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function decoupleHostingByDomain(string $domain): JsonResponse
    {
        /** @var DomainDeployment|null $domainDeployment */
        $domainDeployment = DomainDeployment::query()
            ->whereHas('subscription', function (SubscriptionQueryBuilder $query) use ($domain) {
                $query->where('domain', $domain);
            })
            ->first();

        if ($domainDeployment === null) {
            throw new UnauthorizedException();
        }

        $dnsSubscription = $this->subscriptionRepository->getSubscriptionByCustomerDomainAndType(
            $domainDeployment->subscription->customer,
            $domain,
            ProductGroupType::DNS,
        );

        if ($dnsSubscription === null) {
            throw new UnauthorizedException();
        }

        $this->productPolicy->assertCanCoupleHosting($dnsSubscription);
        $this->subscriptionPolicy->assertCanManageDomain($domainDeployment->subscription);

        try {
            $this->hostingService->decoupleHostingByDomain($domainDeployment);
        } catch (CoupleHostingException $exception) {
            $this->logger->error(
                'Could not decouple domain {domain.name} from Domainsubscription',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                ],
            );

            return new JsonResponse([
                'message' => $this->translator->translate('hosting.decouple-domain-not-coupled'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (DirectAdminCommandException $exception) {
            $this->logger->error(
                'Could not remove {domain.name} from DirectAdmin.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                ],
            );

            return new JsonResponse([
                'message' => $this->translator->translate('hosting.decouple-failed'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['status' => $this->translator->translate('status.success')]);
    }

    /**
     * @throws AuthorizationException
     * @throws HostingException
     * @throws AuthenticationException
     */
    public function getCoupledHostingByDomain(FindDomainHostingCoupleRequest $request): JsonResponse
    {
        /** @var DomainDeployment $domainDeployment */
        $domainDeployment = DomainDeployment::query()
            ->where('subscription_uuid', $request->input('domain_subscription_uuid'))
            ->first();

        $this->subscriptionPolicy->assertCanManageDomain($domainDeployment->subscription);

        try {
            $foundHostingDeployment = $this->hostingService->getCoupledHostingByDomain($domainDeployment);
        } catch (HostingException $exception) {
            $this->logger->error(
                'Could not get coupled hosting for domain {domain.name}',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domainDeployment->subscription->domain,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                ],
            );

            return new JsonResponse([
                'message' => $this->translator->translate(
                    'hosting-controller.get-coupled-hosting-by-domain.exception.message',
                ),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($foundHostingDeployment === null) {
            return new JsonResponse([], Response::HTTP_NO_CONTENT);
        }

        $this->subscriptionPolicy->assertCanView($foundHostingDeployment->subscription);

        return new JsonResponse([
            'status' => $this->translator->translate('status.success'),
            'hosting_subscription_uuid' => $foundHostingDeployment->subscription->uuid,
        ]);
    }

    /**
     * @throws AuthorizationException
     * @throws HostingException
     * @throws AuthenticationException
     */
    public function addDomainToExistingHosting(AddDomainRequest $request): JsonResponse
    {
        $domainDeployment = DomainDeployment::query()
            ->where('subscription_uuid', $request->domain_subscription_uuid)
            ->first();

        $hostingDeployment = HostingDeployment::query()
            ->where('subscription_uuid', $request->hosting_subscription_uuid)
            ->first();

        if (! $hostingDeployment instanceof HostingDeployment || ! $domainDeployment instanceof DomainDeployment) {
            throw new AuthorizationException();
        }

        $this->subscriptionPolicy->assertCanManageHosting($hostingDeployment->subscription);
        $this->subscriptionPolicy->assertCanManageDomain($domainDeployment->subscription);

        assert($domainDeployment->subscription->domain !== null);
        $dnsSubscription = $this->subscriptionRepository->getSubscriptionByCustomerDomainAndType(
            $domainDeployment->subscription->customer,
            $domainDeployment->subscription->domain,
            ProductGroupType::DNS,
        );

        if ($dnsSubscription === null) {
            throw new UnauthorizedException();
        }

        $this->productPolicy->assertCanCoupleHosting($dnsSubscription);

        $isOk = $this->hostingService->coupleDomainToExistingHosting(
            domainDeployment: $domainDeployment,
            hostingDeployment: $hostingDeployment,
        );

        if ($isOk === false) {
            return new JsonResponse([], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['status' => $this->translator->translate('status.success')]);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function getSsoUrl(
        GenerateHostingSsoUrlRequest $request,
        Subscription $subscription,
    ): JsonResponse {
        $hostingDeployment = $subscription->hostingDeployment;
        assert($hostingDeployment instanceof HostingDeployment);
        $this->subscriptionPolicy->assertCanManageHosting($subscription);

        $redirectToMail = (bool) $request->input('redirectToMail');

        try {
            /** @var string $ipAddress */
            $ipAddress = $request->ip();

            $url = $this->hostingService->getSsoUrl(
                $hostingDeployment,
                $ipAddress,
                $redirectToMail,
            );
        } catch (SsoResolveException|NotImplementedException) {
            return new JsonResponse([
                'message' => $this->translator->translate('hosting.sso-resolve-exception'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'url' => $url,
        ]);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function customerConfig(Subscription $subscription): JsonResponse
    {
        $this->subscriptionPolicy->assertCanManageHosting($subscription);

        $subscription->loadMissing('hostingDeployment');

        Assert::notNull($subscription->hostingDeployment);

        try {
            $config = $this->hostingService->getCustomerConfig($subscription->hostingDeployment);
        } catch (ModelNotFoundException $exception) {
            $this->logger->error((string) $exception);

            return new JsonResponse([
                'message' => 'Could not retrieve hosting package customer config',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // We can add more fields when needed
        return new JsonResponse(
            Arr::only($config, ['dnscontrol', 'ssl', 'ssh']),
        );
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function modifyCustomer(UpdateRequest $request, Subscription $subscription): JsonResponse
    {
        $this->subscriptionPolicy->assertCanManageHosting($subscription);

        $parameters = new Parameters();

        if ($request->has('enableDns')) {
            $parameters->setEnableDns($request->enableDns);
        }

        if ($request->has('enableSsh')) {
            $parameters->setEnableSsh($request->enableSsh);
        }

        if ($request->has('enableSsl')) {
            $parameters->setEnableSsl($request->enableSsl);
        }

        try {
            $this->hostingService->modifyCustomer($subscription, $parameters);
        } catch (Throwable $exception) {
            $this->logger->error((string) $exception);

            return new JsonResponse([
                'message' => 'Could not modify hosting.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse();
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function resetPassword(Subscription $subscription): JsonResponse|Response
    {
        $this->subscriptionPolicy->assertCanManageHosting($subscription);

        $providerSlug = $this->hostingService->getProviderSlug($subscription);

        if ($providerSlug === null) {
            return new JsonResponse([
                'message' => 'No hosting provider is set for this subscription',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response = $this->hostingService->resetPassword(
            $subscription,
            ProviderSlug::from($providerSlug),
        );

        if ($response === []) {
            return new Response('', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        /**
         * The response object has a result property that is only set when an error
         * has occurred on the backend.
         */
        if (array_key_exists('result', $response)) {
            new JsonResponse([
                'message' => $response['result'],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        /**
         * Object with properties: "username", "password" and "domain".
         */
        return new JsonResponse($response);
    }

    public function getUserStats(Subscription $subscription): JsonResponse
    {
        $providerSlug = $this->hostingService->getProviderSlug($subscription);

        if ($providerSlug === null) {
            return new JsonResponse([
                'message' => 'No hosting provider is set for this subscription',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response = $this->hostingService->getUserStats(
            $subscription,
            ProviderSlug::from($providerSlug),
        );

        if ($response === null) {
            return new JsonResponse(['result' => 'Get user statistics - Cant retrieve the user usage statistics.']);
        }

        return new JsonResponse([
            'activeDomains' => $response->activeDomains,
            'subdomains' => $response->subdomains,
            'diskSpace' => $response->diskSpaceInMb,
            'mailBoxes' => $response->mailBoxes,
            'mailLists' => $response->mailLists,
            'mailAutoResponders' => $response->mailAutoResponders,
            'redirects' => $response->redirects,
            'databases' => $response->databases,
            'traffic' => $response->traffic,
        ]);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function wpToolkitSSO(Subscription $subscription): JsonResponse
    {
        $this->subscriptionPolicy->assertCanManageHosting($subscription);
        $this->subscriptionPolicy->assertCanUseWpSso($subscription);

        $hostingDeployment = $subscription->hostingDeployment;
        assert($hostingDeployment instanceof HostingDeployment && is_int($hostingDeployment->wp_installation_id));

        try {
            Assert::notNull($hostingDeployment->server);

            $wpCredentials = $this->wpToolkitService
                ->instantiateClient($hostingDeployment->server)
                ->getWpLogin($hostingDeployment->wp_installation_id);

            if ($wpCredentials === null) {
                return new JsonResponse([
                    'message' => $this->translator->translate('wp-toolkit.error.sso-could-not-be-generated'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return new JsonResponse([
                'loginUrl' => $wpCredentials->loginUrl,
                'username' => $wpCredentials->credentials->login,
                'password' => $wpCredentials->credentials->password,
            ]);
        } catch (LogicException $e) {
            $this->logger->error('Wordpress SSO error', [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::EXCEPTION => $e,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
            ]);

            return new JsonResponse([
                'message' => $this->translator->translate('wp-toolkit.error.sso-could-not-be-generated'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function listHostingDomains(HostingDeployment $hostingDeployment): JsonResponse
    {
        $subscription = $hostingDeployment->subscription;
        $this->subscriptionPolicy->assertCanManageHosting($subscription);

        if ($subscription->product->isSitebuilderProduct()) {
            return new JsonResponse([
                [],
            ], Response::HTTP_NO_CONTENT);
        }

        $providerSlug = $this->hostingService->getProviderSlug($subscription);

        if ($providerSlug === null) {
            return new JsonResponse([
                'message' => $this->translator->translate('dkim.error.could-not-retrieve'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $customerDomains = $this->hostingService->getCustomerDomains($providerSlug, $hostingDeployment);
            $isDomainsExternal = $this->domainService->addIsExternalInformation($customerDomains);
        } catch (DirectAdminCommandException|PleskClientException $exception) {
            $this->logger->warning(
                'Could not retrieve list of domains for hosting deployment {provisioning.id}',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                    LoggingContextKeys::PROVISIONING_ID => $hostingDeployment->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return new JsonResponse([
                [
                    'message' => $this->translator->translate('dkim.error.could-not-retrieve'),
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse($isDomainsExternal);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function dkimRecord(HostingDeployment $hostingDeployment, string $domain): JsonResponse
    {
        $subscription = $hostingDeployment->subscription;
        $this->subscriptionPolicy->assertCanManageHosting($subscription);

        $providerSlug = $this->hostingService->getProviderSlug($subscription);

        if ($providerSlug === null) {
            return new JsonResponse([
                'message' => 'No hosting provider is set for this subscription',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $dkimRecord = $this->hostingService->getDkimRecord($providerSlug, $hostingDeployment, $domain);
        } catch (DirectAdminCommandException|PleskClientException $exception) {
            $this->logger->warning(
                'Could not retrieve dkim record for domain {domain.name}',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                    LoggingContextKeys::PROVISIONING_ID => $hostingDeployment->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return new JsonResponse([
                [
                    'message' => $this->translator->translate('dkim.error.could-not-retrieve'),
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'value' => $dkimRecord?->value,
            'name' => $dkimRecord?->host,
            'enabled' => $dkimRecord !== null,
        ]);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function enableDkim(ToggleDkimRequest $request, HostingDeployment $hostingDeployment): JsonResponse
    {
        $subscription = $hostingDeployment->subscription;
        $this->subscriptionPolicy->assertCanManageHosting($subscription);

        $providerSlug = $this->hostingService->getProviderSlug($subscription);

        if ($providerSlug === null) {
            return new JsonResponse([
                'message' => 'No hosting provider is set for this subscription',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $domain = $request->input('domain');
            Assert::string($domain);

            $dnsDeployment = $this->dnsDeploymentRepository->getDnsDeploymentFromDomain($domain);
            $driver = $providerSlug;
            $enabled = $request->boolean('enabled');
            $updateDnsRecord = $dnsDeployment !== null && $dnsDeployment->nameserver_type !== NameserverType::EXTERNAL;
            $dkimRecord = null;

            if (! $enabled && $updateDnsRecord) {
                /*
                 * Retrieve dkim record before disabling on Plesk/DA, because this needs to be removed from PowerDNS,
                 * but can no longer be retrieved from Plesk/DA after it has been disabled.
                 */
                $dkimRecord = $this->hostingService->getDkimRecord($driver, $hostingDeployment, $domain);
            }

            $this->hostingService->setDkim($driver, $hostingDeployment, $domain, $enabled);

            if (! $enabled && $updateDnsRecord && $dkimRecord !== null) {
                $this->dnsService->deleteRecordFromObject(
                    $domain,
                    new DefaultRecord(
                        type: $dkimRecord->type,
                        name: $dkimRecord->host,
                        content: $dkimRecord->value,
                        ttl: self::TTL,
                    ),
                );
            }

            if ($enabled && $updateDnsRecord) {
                $dkimRecord = $this->hostingService->getDkimRecord($driver, $hostingDeployment, $domain);

                if ($dkimRecord !== null) {
                    $this->dnsService->addRecordFromObject(
                        $domain,
                        new DefaultRecord(
                            type: $dkimRecord->type,
                            name: $dkimRecord->host,
                            content: $dkimRecord->value,
                            ttl: self::TTL,
                        ),
                    );
                }
            }
        } catch (
            DirectAdminCommandException|PleskClientException|DnsZoneNotFoundException|PdnsResponseException|GuzzleException|JsonException $exception
        ) {
            $this->logger->warning(
                'Could not toggle dkim for domain {domain.name}',
                [
                    LoggingContextKeys::DOMAIN_NAME => $request->string('domain'),
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                    LoggingContextKeys::PROVISIONING_ID => $hostingDeployment->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return new JsonResponse([
                [
                    'message' => $this->translator->translate('dkim.error.could-not-retrieve'),
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            [
                'message' => $this->translator->translate('status.success'),
            ],
        ]);
    }
}
