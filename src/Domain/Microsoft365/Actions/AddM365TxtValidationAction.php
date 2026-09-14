<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Actions;

use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Microsoft365\Helpers\Microsoft365Helper;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class AddM365TxtValidationAction
{
    private const string SUBDOMAIN_FOR_TXT_VALIDATION = 'kpnonboardingpac';

    private const string MICROSOFT_DOMAIN = 'onmicrosoft.com';

    public function __construct(
        private readonly Microsoft365Service $microsoft365Service,
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
        private readonly DnsService $dnsService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Skips adding DNS record if we can't find a DNS subscription based on the primary domain or if anything fails.
     * When this happens, the provisioning will fail, but it will be more clear what needs to be solved by support agent.
     */
    public function execute(Microsoft365CustomerInfo $customerInfo): void
    {
        Assert::string($customerInfo->kpn_customer_id);
        $tenantName = $this->formatTenantName($customerInfo->tenant_name);
        $kpnCustomerNumber = Microsoft365Helper::customerIdToStringWithoutPrefix($customerInfo->kpn_customer_id);

        $this->logger->debug(
            'Starting with adding TXT record for M365 DNS validation.',
            [
                LoggingContextKeys::META => [
                    'm365_customer_info_id' => $customerInfo->id,
                    'm365_tenant' => $tenantName,
                    'm365_kpn_customer_id' => $kpnCustomerNumber,
                ],
            ],
        );

        try {
            $tenantDefaultDomainName = $this->microsoft365Service->getTenantDefaultDomainName($tenantName);
            $dnsDeployment = $this->dnsDeploymentRepository->getDnsDeploymentFromDomain($tenantDefaultDomainName);
            $domain = $dnsDeployment?->subscription->domain;

            if ($domain === null) {
                $this->logger->warning(
                    'Could not find DNS subscription for microsoft tenant to set TXT validation.',
                    [
                        LoggingContextKeys::META => [
                            'm365_customer_info_id' => $customerInfo->id,
                            'm365_tenant' => $tenantName,
                            'm365_kpn_customer_id' => $kpnCustomerNumber,
                        ],
                    ],
                );

                return;
            }

            $this->dnsService->addRecordFromObject(
                $domain,
                new DefaultRecord(
                    DnsRecordType::TXT->value,
                    $this->getNameForDnsRecord($domain),
                    $kpnCustomerNumber,
                    3600,
                ),
            );

            /** @phpstan-ignore-next-line */
        } catch (Throwable $exception) {
            $this->logger->error(
                'Failed to add DNS record for M365 TXT record validation.',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'm365_customer_info_id' => $customerInfo->id,
                        'm365_tenant' => $tenantName,
                        'm365_kpn_customer_id' => $kpnCustomerNumber,
                    ],
                ],
            );
        }
    }

    private function formatTenantName(?string $tenantName): string
    {
        if (is_string($tenantName) && str_ends_with($tenantName, '.' . self::MICROSOFT_DOMAIN) === false) {
            $tenantName .= '.' . self::MICROSOFT_DOMAIN;
        }

        return (string) $tenantName;
    }

    private function getNameForDnsRecord(string $domain): string
    {
        return self::SUBDOMAIN_FOR_TXT_VALIDATION . '.' . $domain;
    }
}
