<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Jobs;

use Exception;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Providers\ProviderRepository;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Sitebuilder\Factories\SitebuilderServiceFactory;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;
use Webmozart\Assert\Assert;

class ResetDnsTemplateJob extends AbstractQueueableJob
{
    private const int TTL = 3600;

    public function __construct(
        private readonly DnsDeployment $dnsDeployment,
        private readonly HostingDeployment $hostingDeployment,
    ) {
        parent::__construct();
    }

    public function failed(): void
    {
        $this->dnsDeployment->subscription->technical_status = TechnicalStatus::FAILED->value;
        $this->dnsDeployment->subscription->save();
    }

    public function handle(
        LoggerInterface $logger,
        HostingServiceFactory $hostingServiceFactory,
        DnsService $dnsService,
        HostingDeploymentRepository $hostingDeploymentRepository,
        SitebuilderServiceFactory $sitebuilderServiceFactory,
        ProviderRepository $providerRepository,
        SitebuilderService $sitebuilderService,
    ): void {
        $hostingSubscription = $this->hostingDeployment->subscription;
        $domain = $hostingSubscription->domain;
        Assert::notNull($domain);
        $server = $hostingDeploymentRepository->getServer($this->hostingDeployment);
        Assert::notNull($server);

        $logger->debug(
            'Start resetting DNS template for domain {domain.name}',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::PROVISIONING_ID => $this->hostingDeployment->id,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
                LoggingContextKeys::SUBSCRIPTION_ID => $hostingSubscription->id,
            ]
        );

        try {
            $records = $dnsService->getDnsRecordsForDomain($domain);
            foreach ($records as $record) {
                $dnsService->removeDnsRecord($domain, $record);
            }

            $provider = $this->hostingDeployment->provider ?? $this->hostingDeployment->mailProvider;
            Assert::isInstanceOf($provider, Provider::class);

            if ($hostingSubscription->product->isSitebuilderProduct()) {
                $defaultProvider = $sitebuilderServiceFactory->getDefaultSitebuilderProvider();
                $mailOnlyProvider = $providerRepository->getEnabledDefaultByType(ProviderType::MAILONLY);
                $server = $sitebuilderService->getSitebuilderServer($defaultProvider);
                $mailOnlyServer = $sitebuilderService->getMailOnlyServer($mailOnlyProvider);

                $hostingServiceFactory->driver($provider->slug)->resetDnsForSitebuilder($server, $mailOnlyServer, $domain);
            } else {
                $hostingServiceFactory->driver($provider->slug)->setDnsForHosting($server, $domain);
            }

            $logger->debug(
                'Successfully reset DNS template for domain {domain.name}',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_ID => $this->hostingDeployment->id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
                    LoggingContextKeys::SUBSCRIPTION_ID => $hostingSubscription->id,
                ]
            );

            if (! $hostingSubscription->product->isSitebuilderProduct()) {
                $dkimRecord = $hostingServiceFactory->driver($provider->slug)->getDkimRecord($this->hostingDeployment, $domain);
                $logger->debug(
                    'Retrieved DKIM record for domain {domain.name}',
                    [
                        LoggingContextKeys::DOMAIN_NAME => $domain,
                        LoggingContextKeys::PROVISIONING_ID => $this->hostingDeployment->id,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
                        LoggingContextKeys::SUBSCRIPTION_ID => $hostingSubscription->id,
                        LoggingContextKeys::META => [
                            'dkim_record' => $dkimRecord === null ? null : [
                                'type' => $dkimRecord->type,
                                'host' => $dkimRecord->host,
                                'value' => $dkimRecord->value,
                            ],
                        ],
                    ]
                );
                if ($dkimRecord !== null) {
                    $dnsService->addRecordFromObject(
                        $domain,
                        new DefaultRecord(
                            type: $dkimRecord->type,
                            name: $dkimRecord->host,
                            content: $dkimRecord->value,
                            ttl: self::TTL,
                        )
                    );
                    $logger->debug(
                        'Added DKIM record to DNS for domain {domain.name}',
                        [
                            LoggingContextKeys::DOMAIN_NAME => $domain,
                            LoggingContextKeys::PROVISIONING_ID => $this->hostingDeployment->id,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
                            LoggingContextKeys::SUBSCRIPTION_ID => $hostingSubscription->id,
                            LoggingContextKeys::META => [
                                'dkim_record' => [
                                    'type' => $dkimRecord->type,
                                    'host' => $dkimRecord->host,
                                    'value' => $dkimRecord->value,
                                ],
                            ],
                        ]
                    );
                }
            }

            $this->dnsDeployment->last_result = (string) json_encode(['message' => 'Dns reset success']);
            $this->dnsDeployment->save();
        } catch (Exception $exception) { // @phpstan-ignore-line
            $logger->error(
                'Failed to reset DNS template for {domain.name}',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_ID => $this->hostingDeployment->id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
                    LoggingContextKeys::SUBSCRIPTION_ID => $hostingSubscription->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );

            $this->dnsDeployment->last_result = (string) json_encode(['message' => 'Dns reset failed', 'error' => $exception->getMessage()]);
            $this->dnsDeployment->save();

            $this->failed();
        }
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::DNS;
    }
}
