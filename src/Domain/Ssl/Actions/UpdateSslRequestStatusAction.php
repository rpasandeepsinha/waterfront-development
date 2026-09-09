<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Actions;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RealtimeRegister\Domain\Certificate;
use RealtimeRegister\Domain\Enum\ProcessStatusEnum;
use RealtimeRegister\Domain\Enum\StatusEnum;
use RealtimeRegister\RealtimeRegister;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Ssl\Exceptions\SslRequestStatusException;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CsrManager;
use Waterfront\Domain\Ssl\ValueObjects\SslProduct;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class UpdateSslRequestStatusAction
{
    public function __construct(
        private readonly RealtimeRegister $rtrClient,
        private readonly CsrManager $csrManager,
    ) {
    }

    /**
     * @throws SslRequestStatusException
     */
    public function execute(SslDeployment $sslDeployment): void
    {
        $subscription = $sslDeployment->subscription;
        $sslDomain = $subscription->domain;
        if ($sslDomain === null) {
            throw new SslRequestStatusException('No domain for subscription');
        }

        if ($sslDeployment->provider->slug !== ProviderSlug::REALTIME_REGISTER) {
            throw new SslRequestStatusException('SSL deployment is not RTR');
        }

        if ($sslDeployment->request_id === null) {
            throw new SslRequestStatusException('Certificate has not been ordered yet');
        }

        try {
            if ($this->isWildcard($subscription)) {
                $sslDomain = '*.' . $sslDomain;
            }
        } catch (ModelNotFoundException $exception) {
            throw new SslRequestStatusException('SSL product spec is missing', previous: $exception);
        }

        try {
            $requestId = $sslDeployment->request_id;
            $requestInfo = $this->rtrClient->processes->get($requestId);

            if ($requestInfo->status !== ProcessStatusEnum::STATUS_COMPLETED) {
                throw new SslRequestStatusException('Certificate process is not completed');
            }

            $rtrCertificate = $this->getCertificate($subscription, $sslDomain);

            if ($rtrCertificate === null) {
                throw new SslRequestStatusException('Certificate not found');
            }

            if (! $this->compareCsr($sslDomain, $rtrCertificate)) {
                throw new SslRequestStatusException('Stored CSR does not match');
            }

            $sslDeployment->certificate_id = $rtrCertificate->id;
            $sslDeployment->last_result_received = CarbonImmutable::now();
            $sslDeployment->last_result = json_encode([
                'certificate_status' => 'Certificate ready to be downloaded.',
                'message' => 'Ready for download',
            ], JSON_THROW_ON_ERROR);
            $sslDeployment->save();

            if ($subscription->technical_status === TechnicalStatus::FAILED->value) {
                $subscription->technical_status = TechnicalStatus::OK->value;
                $subscription->save();
            }
        } catch (SslRequestStatusException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw new SslRequestStatusException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    private function compareCsr(string $domain, Certificate $certificate): bool
    {
        $storedCsr = $this->csrManager->getRawCsr($domain);
        $certificateCsr = (string) $certificate->csr;

        return trim($storedCsr) === trim($certificateCsr);
    }

    private function isWildcard(Subscription $subscription): bool
    {
        $productSpec = $subscription->product->productSpecs()->where('name', 'ssl.product_id')
            ->firstOrFail();

        return SslProduct::fromNative($productSpec->value)->isWildcardSsl();
    }

    private function getCertificate(Subscription $subscription, string $sslDomain): ?Certificate
    {
        $productSpec = $subscription->product->productSpecs()->where('name', 'ssl.product_id')
            ->firstOrFail();

        return $this->rtrClient->certificates->listCertificates(
            1,
            0,
            null,
            [
                'order' => '-startDate',
                'domainName:eq' => $sslDomain,
                'product:eq' => $productSpec->value,
                'status:eq' => StatusEnum::STATUS_ACTIVE,
            ]
        )->offsetGet(0);
    }
}
