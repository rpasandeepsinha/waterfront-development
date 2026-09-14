<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Pipelines\CreateCertificate;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Contracts\Bus\Dispatcher;
use JsonException;
use Waterfront\Domain\Ssl\Interfaces\Models\Parameters as CreateParameters;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;
use Waterfront\Domain\Ssl\Jobs\UpdateDns;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CsrManager;
use Waterfront\Domain\Ssl\ValueObjects\SslProduct;

class CreateCertificatePipeline
{
    public function __construct(
        private readonly GenerateCsrStep $generateCsrStep,
        private readonly CreateCertificateStep $createCertificateStep,
        private readonly UpdateSubscriptionStep $createSubscriptionStep,
        private readonly UpdateDnsStep $updateDnsStep,
        private readonly CsrManager $csrManager,
        private readonly Dispatcher $jobDispatcher,
    ) {
    }

    /**
     * @param mixed[] $customerData
     *
     * @throws JsonException
     */
    public function process(
        SslProduct $sslProduct,
        int $periodInMonths,
        string $domain,
        string $sslDomain,
        array $customerData,
        string $subscriptionUuid,
        ?string $csr = null,
    ): Result {
        $customCsr = ! is_null($csr);
        $csrKey = $this->csrStep($customerData, $domain, $sslDomain, $csr);

        $parameters = $this->parameterStep($sslProduct, $periodInMonths, $sslDomain, $customerData, $csrKey);
        $result = $this->createCertificateStep->execute($parameters);
        $sslDeployment = $this->createSubscriptionStep->execute($result, $subscriptionUuid, $customCsr);

        $this->updateSubscription($sslDeployment, $result, $customCsr);

        $this->dnsChecker($result, $domain);

        return $result;
    }

    public function updateSubscription(SslDeployment $sslDeployment, Result $result, bool $customCsr): void
    {
        $sslDeployment->last_result_received = CarbonImmutable::now();
        $sslDeployment->last_result = json_encode($result->toArray(), JSON_THROW_ON_ERROR);
        $sslDeployment->custom_csr = $customCsr;
        $sslDeployment->save();
    }

    public function dnsChecker(Result $result, string $domain): void
    {
        if (! is_null($result->getDnsValue())) {
            // DV certificates are active
            $this->updateDnsStep->execute($result, $domain);
        } else {
            // Keep checking requested (most likely EV) certificate
            $this->jobDispatcher->dispatch(new UpdateDns($domain));
        }
    }

    /**
     * @param mixed[] $customerData
     *
     * @throws Exception
     */
    public function csrStep(array $customerData, string $domain, string $sslDomain, ?string $csr): string
    {
        if (is_null($csr)) {
            $csrKey = $this->generateCsrStep->execute($customerData, $sslDomain);
        } else {
            $this->csrManager->storeUserSupplied($csr, $domain);
            $csrKey = $csr;
        }

        return $csrKey;
    }

    /**
     * @param mixed[] $customerData
     *
     * @throws JsonException
     */
    private function parameterStep(
        SslProduct $sslProduct,
        int $periodInMonths,
        string $sslDomain,
        array $customerData,
        string $csrKey,
    ): CreateParameters {
        $periodInYears = (int) ceil($periodInMonths / 12);

        return CreateParameters::create(
            [
                'domain' => $sslDomain,
                'customer' => $customerData,
                'productId' => $sslProduct->toNative(),
                'period' => $periodInYears,
                'csr' => $csrKey,
            ],
        );
    }
}
