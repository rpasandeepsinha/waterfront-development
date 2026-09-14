<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Services;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Psr\Log\LoggerInterface;
use SandwaveIo\Office365\Exception\Office365Exception;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Microsoft365\Exceptions\TenantNameTakenException;
use Waterfront\Domain\Microsoft365\Helpers\Microsoft365Helper;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365Repository;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

/**
 * @see \Tests\Domain\Microsoft365\Services\Microsoft365KpnOnBoardingServiceTest
 */
class Microsoft365KpnOnboardingService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Microsoft365Service $microsoft365Service,
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
        private readonly DnsService $dnsService,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly Microsoft365Repository $microsoft365Repository,
    ) {
    }

    /**
     * @throws Office365Exception
     * @throws JsonException
     * @throws TenantNameTakenException
     * @throws DnsZoneNotFoundException
     * @throws GuzzleException
     */
    public function handleKpnOnBoardingPac(string $log, Microsoft365Deployment $microsoft365Deployment): void
    {
        Assert::notNull($microsoft365Deployment->microsoft365CustomerInfo->tenant_name);
        $defaultDomain = $this->microsoft365Service->getTenantDefaultDomainName($microsoft365Deployment->microsoft365CustomerInfo->tenant_name);

        $dnsDeployment = $this->dnsDeploymentRepository->getDnsDeploymentFromDomain($defaultDomain);

        if (! $dnsDeployment instanceof DnsDeployment) {
            $this->logger->error(
                'No DNS deployment found for Microsoft 365 with primary domain {microsoft365.primary_domain}',
                [
                    LoggingContextKeys::META => [
                        'microsoft365.deployment_id' => $microsoft365Deployment->id,
                        'microsoft365.primary_domain' => $defaultDomain,
                    ],
                ],
            );
            $this->subscriptionRepository->setTechnicalStatus(
                $microsoft365Deployment->subscription,
                TechnicalStatus::FAILED->value,
            );

            return;
        }

        preg_match('/KPNOnboardingPac\s*-\s*\d+/', $log, $kpnOnBoardingPacMatch);

        if (count($kpnOnBoardingPacMatch) !== 1) {
            $this->logger->error(
                'Could not find customer number in the microsoft365 log',
                [
                    LoggingContextKeys::META => [
                        'microsoft365.deployment_id' => $microsoft365Deployment->id,
                        'microsoft365.log' => $log,
                    ],
                ],
            );
            $this->subscriptionRepository->setTechnicalStatus(
                $microsoft365Deployment->subscription,
                TechnicalStatus::FAILED->value,
            );

            return;
        }

        $kpnCustomerId = str_replace('KPNOnboardingPac - ', '', $kpnOnBoardingPacMatch[0]);

        $kpnOnBoardingPacMicrosoft365CustomerInfo = Microsoft365CustomerInfo::where(
            'kpn_customer_id',
            Microsoft365Helper::customerIdToStringWithPrefix($kpnCustomerId),
        )->first();

        if (! $kpnOnBoardingPacMicrosoft365CustomerInfo instanceof Microsoft365CustomerInfo) {
            $this->logger->error(
                'No Microsoft365 customer info found for KPN onboarding pac {microsoft365.onboarding_pac}',
                [
                    LoggingContextKeys::META => [
                        'microsoft365.deployment_id' => $microsoft365Deployment->id,
                        'microsoft365.onboarding_pac' => $kpnCustomerId,
                    ],
                ],
            );
            $this->subscriptionRepository->setTechnicalStatus(
                $microsoft365Deployment->subscription,
                TechnicalStatus::FAILED->value,
            );

            return;
        }

        $records = $this->dnsService->getDnsRecordsForDomain($defaultDomain);

        $kpnOnBoardingPacRecord = $records->filter(
            fn (DnsRecordInterface $record) => (
                $record->getType() === DnsRecordType::TXT->value
                && $record->getName() === 'kpnonboardingpac.' . $defaultDomain
                && $record->getContent() === $kpnCustomerId
            ),
        );

        if ($kpnOnBoardingPacRecord->count() === 0) {
            $this->dnsService->addRecordFromObject(
                $defaultDomain,
                new DefaultRecord(
                    type: DnsRecordType::TXT->value,
                    name: 'kpnonboardingpac.' . $defaultDomain,
                    content: $kpnCustomerId,
                    ttl: 3600,
                ),
            );
        }

        $this->microsoft365Repository->activeChildrenCount($microsoft365Deployment);

        //        $this->microsoft365Service->createOrder(
        //            customer: $kpnOnBoardingPacMicrosoft365CustomerInfo->customer,
        //            kpnCustomerId: $kpnOnBoardingPacMicrosoft365CustomerInfo->kpn_customer_id,
        //            microsoft365Deployment: $microsoft365Deployment,
        //            productCode: $microsoft365Deployment->subscription->productPrice->kpnProduct->kpn_product_code,
        //            amount: $childCount,
        //        );
    }
}
