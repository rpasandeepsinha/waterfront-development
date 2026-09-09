<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder\Services;

use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Nonstandard\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use SandwaveIo\BaseKit\Domain\Site;
use SandwaveIo\BaseKit\Exceptions\BaseKitClientException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Events\UpdateDns;
use Waterfront\Domain\DNS\Services\DnsZoneService;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSettingKey;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\ProviderRepository;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Sitebuilder\Requests\AddSslSitebuilderRequest;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\DTO\BaseKitSite;
use Waterfront\Domain\Sitebuilder\DTO\BaseKitUser;
use Waterfront\Domain\Sitebuilder\DTO\SitebuilderSiteInterface;
use Waterfront\Domain\Sitebuilder\DTO\SitebuilderUserInterface;
use Waterfront\Domain\Sitebuilder\Exceptions\DomainNotFoundException;
use Waterfront\Domain\Sitebuilder\Exceptions\SitebuilderException;
use Waterfront\Domain\Sitebuilder\Interfaces\SitebuilderDriverInterface;
use Waterfront\Domain\Sitebuilder\Mailer\MailSitebuilderActivation;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CertificateManager;
use Waterfront\Domain\Ssl\Services\CsrManager;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\GatewayHelper;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class BaseKitService implements SitebuilderDriverInterface
{
    private const string LOCALE = 'nl';

    public function __construct(
        private readonly CertificateManager $certificateManager,
        private readonly CsrManager $csrManager,
        private readonly DnsZoneService $dnsZoneService,
        private readonly BasekitFactoryInterface $basekitFactory,
        private readonly MailerInterface $mailer,
        private readonly Dispatcher $eventDispatcher,
        private readonly ProviderRepository $providerRepository,
        private readonly ProvisionGateway $provisionGateway,
        private readonly LoggerInterface $logger,
        private readonly GatewayHelper $gatewayHelper,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function createSite(Subscription $subscription, Server $server, Server $mailOnlyServer): Result
    {
        $customer = $subscription->customer;
        $domain = $subscription->domain;

        Assert::notNull($domain, 'Provided subscription has no domain');

        $result = new Result();
        $sitebuilderProvider = $this->providerRepository->getEnabledDefaultByType(ProviderType::SITEBUILDER);
        $basekitBrandReference = $this->providerRepository->getSettingByKey($sitebuilderProvider, ProviderSettingKey::BRANDREFERENCE);
        $basekitPackageReference = $this->providerRepository->getSettingByKey($sitebuilderProvider, ProviderSettingKey::PACKAGEREFERENCE);

        $userRef = $this->createUser($customer, (int) $basekitBrandReference->value, $domain, $server);
        $this->addUserPackage($userRef, (int) $basekitPackageReference->value, $subscription->contract_period, $server);
        $externalSite = $this->createExternalSite($domain, $userRef, (int) $basekitBrandReference->value, $server);

        $ipv4Host = $server->getIpv4();
        $ipv6Host = $server->getIpv6();

        $ipv4HostMail = $mailOnlyServer->getIpv4();
        $ipv6HostMail = $mailOnlyServer->getIpv6();
        if ($ipv4Host === null) {
            throw new SitebuilderException(
                'Feature was not configured correctly. Make sure the BASEKIT_HOST_IPV4 value is configured.'
            );
        }

        $changes = $this->dnsZoneService->getExternalHostingDnsRecords($domain, $ipv4Host, $ipv6Host, $ipv4HostMail, $ipv6HostMail);

        $this->eventDispatcher->dispatch(new UpdateDns($domain, $changes));

        $result->setStatus(Result::STATUS_OK);
        $result->setResourceId((string) $userRef);
        $result->setResponseBody($externalSite->toArray());
        $result->setServerId($server->id);

        $this->sendActivationEmail($subscription);

        return $result;
    }

    public function getSite(HostingDeployment $hostingDeployment): SitebuilderSiteInterface
    {
        $baseKitSiteRef = $hostingDeployment->basekit_site_ref;

        if (is_null($baseKitSiteRef)) {
            throw new SitebuilderException(
                sprintf(
                    'Hosting subscription %s (hosting deployment: %d) has no site ref',
                    $hostingDeployment->subscription->domain,
                    $hostingDeployment->id
                )
            );
        }

        /** @var Server $server */
        $server = $hostingDeployment->basekitServer()->firstOrFail();

        $baseKitClient = $this->basekitFactory->make($server);

        $baseKitSite = $baseKitClient->sitesApi->get($baseKitSiteRef);

        return new BaseKitSite(
            id: $baseKitSite->ref,
            domain: $baseKitSite->primaryDomain->domainName,
        );
    }

    public function getSiteFromRef(int $siteRef, Server $server): SitebuilderSiteInterface
    {
        $baseKitClient = $this->basekitFactory->make($server);

        $baseKitSite = $baseKitClient->sitesApi->get($siteRef);

        return new BaseKitSite(
            id: $baseKitSite->ref,
            domain: $baseKitSite->primaryDomain->domainName
        );
    }

    public function getUserFromRef(int $userRef, Server $server): SitebuilderUserInterface
    {
        $baseKitClient = $this->basekitFactory->make($server);

        $baseKitAccountHolder = $baseKitClient->userApi->get($userRef);

        return new BaseKitUser(
            id: $baseKitAccountHolder->ref,
            email: $baseKitAccountHolder->email,
        );
    }

    public function deleteSite(HostingDeployment $hostingDeployment, Server $server): void
    {
        if (is_null($hostingDeployment->basekit_site_ref)) {
            throw new SitebuilderException(
                sprintf(
                    'Hosting subscription %s (%d) has no site ref',
                    $hostingDeployment->subscription->domain,
                    $hostingDeployment->id
                )
            );
        }

        $basekitClient = $this->basekitFactory->make($server);

        $basekitClient->sitesApi->delete($hostingDeployment->basekit_site_ref);

        // Basekit will give error if the delete does not go as planned.
        $hostingDeployment->subscription->technical_status = TechnicalStatus::DELETED->value;
        $hostingDeployment->subscription->save();
    }

    /**
     * @throws RuntimeException
     * @throws DomainNotFoundException
     * @throws FileNotFoundException
     * @throws Exception
     */
    public function setupSsl(SslDeployment $sslDeployment, Server $server): Result
    {
        $sslSubscription = $sslDeployment->subscription;
        $domain = $sslSubscription->domain;

        if (is_null($domain)) {
            throw new DomainNotFoundException(
                sprintf(
                    'Failed to fetch domain for SSL Subscription {%s} ',
                    $sslDeployment->id
                )
            );
        }

        $siteBuilderSubscription = $this->subscriptionRepository->getSubscriptionByDomainAndGroup($domain, ProductGroupType::HOSTING);

        if (! $siteBuilderSubscription->product->isSitebuilderProduct()) {
            throw new Exception(
                sprintf(
                    'Failed to fetch sitebuilder subscription for domain {%s} ',
                    $domain
                )
            );
        }

        $rootCertificate = $this->certificateManager->getRootCertificate($domain);

        if (is_null($rootCertificate)) {
            throw new RuntimeException(
                sprintf(
                    'Failed to fetch root certificate for {%s} ',
                    $domain
                )
            );
        }

        $mainCertificate = $this->certificateManager->getMainCertificate($domain);

        if (is_null($mainCertificate)) {
            throw new RuntimeException(
                sprintf(
                    'Failed to fetch main certificate for {%s} ',
                    $domain
                )
            );
        }

        $privateKey = $this->csrManager->getPrivateKey($domain);

        if ($this->gatewayHelper->hasSitebuilderDeploymentUsingGateway($siteBuilderSubscription)) {
            return $this->addSslUsingGateway(
                tag: Uuid::fromString($siteBuilderSubscription->uuid),
                sslSubscription: $sslSubscription,
                privateKey: $privateKey,
                mainCertificate: $mainCertificate,
            );
        }

        $basekitClient = $this->basekitFactory->make($server);
        $result = new Result();

        try {
            $basekitClient->sslApi->addSsl($domain, $privateKey, $mainCertificate);

            $result->setStatus($result::STATUS_OK);
        } catch (SitebuilderException $sitebuilderException) {
            $errorMessage = sprintf(
                'Failed to setup SSL for {%s}. Api returned {%s}',
                $domain,
                $sitebuilderException->getMessage()
            );

            $result->setStatus($result::STATUS_ERROR);
            $result->setErrorMessage($errorMessage);

            $this->logger->error(
                $errorMessage,
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $siteBuilderSubscription->uuid,
                    LoggingContextKeys::META => [
                        'ssl_subscription_uuid' => $sslSubscription->uuid,
                        'sitebuilder_subscription_uuid' => $siteBuilderSubscription->uuid,
                    ],
                ]
            );
        }
        return $result;
    }

    public function getSsoUrl(Server $server, string $domain, int $basekitUserRef, int $basekitSiteRef): string
    {
        $basekitClient = $this->basekitFactory->make($server);
        try {
            $hash = $basekitClient->loginApi->autoLogin($basekitUserRef);
        } catch (BaseKitClientException $exception) {
            $this->logger->error(
                sprintf(
                    'Error: %s,for domain: %s with basekit user ref: %s',
                    $exception->getMessage(),
                    $domain,
                    $basekitUserRef,
                ),
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SERVER_ID => $server->id,
                    LoggingContextKeys::META => [
                        'basekit_user_ref' => $basekitUserRef,
                        'basekit_site_ref' => $basekitSiteRef,
                    ],
                ]
            );
            throw new SitebuilderException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return sprintf('https://flow.%s/login?hash=%s&siteRef=%s', $server->domain, $hash, $basekitSiteRef);
    }

    private function createUser(Customer $customer, int $brandRef, string $domain, Server $server): int
    {
        $basekitClient = $this->basekitFactory->make($server);
        $username = $domain . '-' . $customer->customer_number . '-' . Str::random(10);
        $password = Str::random(35);

        try {
            $accountHolder = $basekitClient->userApi->create(
                $brandRef,
                $customer->first_name,
                $customer->last_name,
                $username,
                $password,
                $customer->email,
                self::LOCALE,
            );
        } catch (BaseKitClientException $exception) {
            $this->logger->error(
                sprintf(
                    'Error: %s , For customer number: %s',
                    $exception->getMessage(),
                    $customer->customer_number
                ),
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SERVER_ID => $server->id,
                    LoggingContextKeys::CUSTOMER_NUMBER => $customer->customer_number,
                    LoggingContextKeys::CUSTOMER_ID => $customer->id,
                    LoggingContextKeys::META => [
                        'basekit_brandref' => $brandRef,
                    ],
                ]
            );
            throw new SitebuilderException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return $accountHolder->ref;
    }

    private function addUserPackage(int $userRef, int $packageRef, int $subscriptionPeriod, Server $server): void
    {
        $basekitClient = $this->basekitFactory->make($server);
        try {
            $basekitClient->packageApi->addUserPackage($userRef, $packageRef, $subscriptionPeriod);
        } catch (BaseKitClientException $exception) {
            $this->logger->error(
                sprintf(
                    'Error: %s, for UserRef: %s and packageRef: %s.',
                    $exception->getMessage(),
                    $userRef,
                    $packageRef
                ),
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
                    LoggingContextKeys::SERVER_ID => $server->id,
                    LoggingContextKeys::META => [
                        'basekit_user_ref' => $userRef,
                        'basekit_package_ref' => $packageRef,
                    ],
                ]
            );
            throw new SitebuilderException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    private function createExternalSite(string $domain, int $userRef, int $brandRef, Server $server): Site
    {
        $basekitClient = $this->basekitFactory->make($server);
        try {
            $site = $basekitClient->sitesApi->create(
                $userRef,
                $brandRef,
                $domain
            );
        } catch (BaseKitClientException $exception) {
            $this->logger->error(
                sprintf(
                    'Error: %s, for domain: %s with userRef: %s.',
                    $exception->getMessage(),
                    $domain,
                    $userRef
                ),
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SERVER_ID => $server->id,
                    LoggingContextKeys::META => [
                        'basekit_user_ref' => $userRef,
                        'basekit_brand_ref' => $brandRef,
                    ],
                ]
            );
            throw new SitebuilderException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return $site;
    }

    private function sendActivationEmail(Subscription $subscription): void
    {
        $this->mailer->send(
            [$subscription->customer],
            new MailSitebuilderActivation($subscription->domain ?? '')
        );
    }

    private function addSslUsingGateway(UuidInterface $tag, Subscription $sslSubscription, string $privateKey, string $mainCertificate): Result
    {
        $result = new Result();

        $request = new AddSslSitebuilderRequest(
            tagUuid: $tag,
            context: $tag,
            privateKey: $privateKey,
            mainCertificate: $mainCertificate,
        );

        $provisionResult = $this->provisionGateway->request($request);

        if ($provisionResult->failed) {
            $this->logger->error(
                'Failed installing SSL certificate on basekit deployment',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $tag->toString(),
                    LoggingContextKeys::DOMAIN_NAME => $sslSubscription->domain,
                    LoggingContextKeys::EXCEPTION => $provisionResult->exception,
                ]
            );

            $sslSubscription->technical_status = TechnicalStatus::FAILED->value;
            $sslSubscription->save();

            $errorMessage = sprintf(
                'Failed to setup SSL for {%s}. Api returned {%s}',
                $sslSubscription->domain,
                $provisionResult->exception?->getMessage() ?? ''
            );

            $result->setStatus($result::STATUS_ERROR);
            $result->setErrorMessage($errorMessage);

            return $result;
        }

        $this->logger->info('Installing SSL certificate on basekit deployment successful', [
            LoggingContextKeys::SUBSCRIPTION_UUID => $tag->toString(),
            LoggingContextKeys::DOMAIN_NAME => $sslSubscription->domain,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
        ]);

        $sslSubscription->technical_status = TechnicalStatus::OK->value;
        $sslSubscription->save();

        $result->setStatus($result::STATUS_OK);
        return $result;
    }
}
