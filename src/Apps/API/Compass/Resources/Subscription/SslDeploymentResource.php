<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Subscription;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Routing\UrlGenerator;
use JsonException;
use Waterfront\Apps\API\Waterfront\Resources\BaseTechnicalDeploymentResource;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CertificateRetriever;

class SslDeploymentResource
{
    public function __construct(
        private readonly BaseTechnicalDeploymentResource $baseTechnicalDeploymentResource,
        private readonly CertificateRetriever $certificateRetriever,
        private readonly UrlGenerator $urlGenerator,
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
        ];
    }

    /**
     * @throws JsonException
     */
    public function toJson(SslDeployment $sslDeployment): string
    {
        return json_encode($this->toArray($sslDeployment), flags: JSON_THROW_ON_ERROR);
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
            $resource['certificates'][$name] = $this->urlGenerator->route('admin.ssl.download', [
                'type' => $extension,
                'sslDeployment' => $subscription->uuid,
            ]);
        }

        return $resource;
    }
}
