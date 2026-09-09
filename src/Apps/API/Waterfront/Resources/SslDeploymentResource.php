<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Routing\UrlGenerator;
use JsonException;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CertificateRetriever;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\RtrClient\Services\RtrSslService;

class SslDeploymentResource
{
    public function __construct(
        private readonly BaseTechnicalDeploymentResource $baseTechnicalDeploymentResource,
        private readonly CertificateRetriever $certificateRetriever,
        private readonly UrlGenerator $urlGenerator,
        private readonly RtrSslService $rtrSslService,
        private readonly DomainDeploymentRepository $domainDeploymentRepository,
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
        private readonly PublicSuffixList $publicSuffixList,
    ) {
    }

    /**
     * @return array <string, mixed>
     */
    public function toArray(SslDeployment $sslDeployment): array
    {
        return [
            ...$this->baseTechnicalDeploymentResource->toArray($sslDeployment),
            ...$this->addCertificates($sslDeployment),
            'has_custom_csr' => $sslDeployment->custom_csr,
            'has_reissued' => $sslDeployment->has_reissued,
            'last_status' => $sslDeployment->status ?? 'UNKNOWN',
            ...$this->addCnameRecord($sslDeployment),
        ];
    }

    /**
     * @throws JsonException
     */
    public function toJson(SslDeployment $sslDeployment): string
    {
        return json_encode($this->toArray($sslDeployment), flags:JSON_THROW_ON_ERROR);
    }

    /**
     * @throws FileNotFoundException
     *
     * @return array<string, array<string>|bool|string>
     */
    private function addCertificates(SslDeployment $sslDeployment): array
    {
        $subscription = $sslDeployment->subscription;
        $resource = [];
        $resource['certificates'] = [];
        foreach ($this->certificateRetriever->getAvailableCertificateTypes($sslDeployment) as $name => $extension) {
            $resource['certificates'][$name] = $this->urlGenerator->route('partners.ssl.download', [
                'type' => $extension,
                'uuid' => $subscription->uuid,
            ]);
        }

        return $resource;
    }

    /**
     * @return array<string, string>
     */
    private function addCnameRecord(SslDeployment $sslDeployment): array
    {
        $resource = [];

        // don't retrieve cname when the ssl technical status is ok, at this point the certificate is correctly provisioned
        if ($sslDeployment->subscription->domain === null || $sslDeployment->subscription->technical_status === TechnicalStatus::OK->value) {
            return $resource;
        }

        $domain = $this->publicSuffixList->getRegistrableDomain($sslDeployment->subscription->domain);

        if ($domain === null) {
            return $resource;
        }

        $domainDeployment = $this->domainDeploymentRepository->getDomainDeploymentByDomain($domain);

        if ($domainDeployment instanceof DomainDeployment) {
            $dnsDeployment = $this->dnsDeploymentRepository->getDnsDeploymentFromDomainDeployment($domainDeployment);
            if ($dnsDeployment === null) {
                return $resource;
            }

            if ($dnsDeployment->externalNameservers->count() === 0) {
                return $resource;
            }
        }

        $cnameRecord = $this->rtrSslService->getSslCnameRecord($sslDeployment);

        if ($cnameRecord !== null && $cnameRecord->status !== 'VALIDATED') {
            $resource['dnsType'] = $cnameRecord->dnsType;
            $resource['dnsRecord'] = str_replace('.' . $domain . '.', '', $cnameRecord->dnsRecord);
            $resource['dnsContent'] = $cnameRecord->dnsContent;
            $resource['dcvStatus'] = $cnameRecord->caaRecordStatus;
        }

        return $resource;
    }
}
