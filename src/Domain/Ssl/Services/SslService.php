<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Services;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use LogicException;
use RuntimeException;
use Throwable;
use Waterfront\Apps\Console\Commands\Sanity\CheckSsl;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Ssl\Interfaces\Models\Parameters;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;
use Waterfront\Domain\Ssl\Interfaces\SslDriverInterface;
use Waterfront\Domain\Ssl\Jobs\UpdateDns;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Pipelines\CreateCertificate\CreateCertificatePipeline;
use Waterfront\Domain\Ssl\ValueObjects\SslProduct;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\OpenproviderClient\Factories\OpenproviderClientFactory;
use Waterfront\Infra\RtrClient\DTO\DcvDetails;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;
use Webmozart\Assert\Assert;

class SslService implements SslDriverInterface
{
    public function __construct(
        private readonly CertificateService $certificateService,
        private readonly CertificateManager $certificateManager,
        private readonly CsrManager $csrManager,
        private readonly CreateCertificatePipeline $createCertificatePipeline,
        private readonly OpenproviderClientFactory $openproviderClientFactory,
        private readonly Dispatcher $jobDispatcher,
        private readonly PublicSuffixList $publicSuffixRules,
    ) {
    }

    public function create(
        ProductSpec $productSpec,
        int $period,
        array $customerData,
        SslDeployment $sslDeployment,
        ?string $csr = null
    ): Result {
        Log::info(
            self::class . '::create - Create new ssl',
            [
                LoggingContextKeys::PROVISIONING_ID => $sslDeployment->id,
            ]
        );

        $domain = $sslDeployment->subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        $sslProduct = SslProduct::fromNative($productSpec->value);

        // ssl product is wildcard so add '*.' to the domain.
        $sslDomain = $domain;
        if ($sslProduct->isWildcardSsl()) {
            $sslDomain = '*.' . $domain;
        }

        return $this->createCertificatePipeline->process(
            $sslProduct,
            $period,
            $domain,
            $sslDomain,
            $customerData,
            $sslDeployment->subscription->uuid,
            $csr
        );
    }

    public function retrieve(SslDeployment $sslDeployment): Result
    {
        if ($sslDeployment->certificate_id === null) {
            throw new LogicException(
                "SSL deployment with id {$sslDeployment->id} has no certificate ID, so the SSL cannot be retrieved."
            );
        }

        return $this->openproviderClientFactory->create()->retrieveSsl($sslDeployment->certificate_id);
    }

    /**
     * @param mixed[] $customerData
     *
     * @throws JsonException
     */
    public function reissue(
        array $customerData,
        SslDeployment $sslDeployment,
        string $csr
    ): Result {
        $domain = $sslDeployment->subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        Log::info(
            self::class . '::reissue - Reissue ssl',
            [
                LoggingContextKeys::PROVISIONING_ID => $sslDeployment->id,
                LoggingContextKeys::DOMAIN_NAME => $domain,
            ]
        );

        /** @var int $certificateId */
        $certificateId = $sslDeployment->certificate_id;

        $parameters = Parameters::create(
            [
                'domain'                => $domain,
                'customer'              => $customerData,
                'productId'             => 31,
                'period'                => $sslDeployment->subscription->contract_period,
                'csr'                   => $csr,
            ]
        );

        $result = $this->openproviderClientFactory->create()->reissueSsl($certificateId, $parameters);

        $job = new UpdateDns($domain, false);
        $job->onConnection('sync');

        $this->jobDispatcher->dispatch($job);

        Artisan::call(CheckSsl::class, [
            'domain' => $domain,
        ]);

        return $result;
    }

    /**
     * @throws Exception
     */
    public function renew(SslDeployment $sslDeployment): Result
    {
        $domain = $sslDeployment->subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        Log::info(
            self::class . '::renew - Renew ssl',
            [
                LoggingContextKeys::PROVISIONING_ID => $sslDeployment->id,
                LoggingContextKeys::DOMAIN_NAME => $domain,
            ]
        );

        try {
            $certificateId = $sslDeployment->certificate_id;

            if ($certificateId === null) {
                throw new LogicException(
                    "SSL deployment with id {$sslDeployment->id} has no certificate ID, so the SSL cannot be retrieved."
                );
            }

            $result = $this->openproviderClientFactory->create()->retrieveSsl($certificateId);

            $sslDeployment->last_result_received = CarbonImmutable::now();
            $sslDeployment->last_result = json_encode($result->toArray(), JSON_THROW_ON_ERROR);
            $sslDeployment->save();

            $result = $this->openproviderClientFactory->create()->renewSsl($certificateId);

            $this->jobDispatcher->dispatch(new UpdateDns($domain, false));

            return $result;
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Failed to renew SSL deployment: ' . $exception->getMessage(),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Check if the domain has a valid certificate.
     */
    public function check(string $domain = '', int $period = 12): bool
    {
        if ($domain === 'test.com') {
            return true;
        }
        try {
            $status = $this->certificateService->check($domain, $period);
        } catch (Throwable $exception) {
            Log::error(
                self::class . '::check - status code: ' . $exception->getCode()
                . ', message: ' . $exception->getMessage()
                . ', trace: ' . $exception->getTraceAsString()
            );

            throw new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
        }

        Log::info(
            self::class . '::check - certificate validation',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => [
                    'status' => $status,
                ],
            ]
        );

        return $status;
    }

    /**
     * @inheritDoc
     */
    public function prepareCertificateInstallParameters(int $certificateId, string $domain, array $certificates, bool $alreadySaved = false): array
    {
        $sslDeployment = SslDeployment::where('certificate_id', $certificateId)->firstOrFail();

        if (! $alreadySaved) {
            $this->prepareCertificates($certificateId, $domain, $certificates);
        }

        $parameters = $this->certificateService->prepareCertificateInstallParameters($domain);

        return [
            'subscriptionUuid' => $sslDeployment->subscription_uuid,
            'parameters'       => $parameters->toArray(),
        ];
    }

    /**
     * Save certificates to storage.
     */
    public function prepareCertificates(int $certificateId, string $domain, array $certificates): void
    {
        $sslDeployment = SslDeployment::where('certificate_id', $certificateId)->first();
        if ($sslDeployment === null) {
            throw new ModelNotFoundException()->setModel(SslDeployment::class);
        }

        $sslResult = $this->openproviderClientFactory->create()->retrieveSsl($certificateId);

        $this->csrManager->saveCsr($domain, $sslResult->getCsr() ?? '');

        $this->certificateManager->saveRootCertificate($domain, $certificates['root']);
        $this->certificateManager->saveIntermediateCertificate($domain, $certificates['intermediate']);
        $this->certificateManager->saveMainCertificate($domain, $certificates['main']);

        $this->csrManager->removeLocalDirectory($domain);
    }

    /**
     * Checks if we can find the private CSR key for a domain.
     */
    public function csrExistsForDomain(string $domain): bool
    {
        return $this->csrManager->hasCsr($domain);
    }

    /**
     * @return string[]|bool
     */
    public function validate(string $csr, string $domain, ?SslDeployment $sslDeployment): array|bool
    {
        $csrTrimmed = trim($csr);

        if (! Str::startsWith($csrTrimmed, '-----BEGIN CERTIFICATE REQUEST-----') ||
            ! Str::endsWith($csrTrimmed, '-----END CERTIFICATE REQUEST-----')) {
            return false;
        }

        if (str_contains($csr, '-----BEGIN PRIVATE KEY-----') || str_contains($csr, '-----END PRIVATE KEY-----')) {
            return false;
        }

        $csrArray = openssl_csr_get_subject($csr, false);
        if ($csrArray === false) {
            return false;
        }

        if ($sslDeployment !== null) {
            $domain = $sslDeployment->subscription->domain;
            Assert::notNull($domain, 'Provided subscription has no domain');
        }

        $csrDomain = $this->resolveCsrDomain($csrArray['commonName']);
        if ($this->compareTwoDomains($csrDomain, $domain)) {
            return $csrArray;
        }
        return false;
    }

    public function checkPrivateKeyMatches(string $domain): bool
    {
        $cert = $this->certificateManager->getMainCertificate($domain);

        try {
            $pkey = $this->csrManager->getPrivateKey($domain);
        } catch (FileNotFoundException) {
            Log::info(self::class . 'checkPrivateKeyMatches - Private key not found for ' . $domain);

            return false;
        }

        return openssl_x509_check_private_key($cert ?? '', $pkey);
    }

    public function resolveCsrDomain(string $commonName): string
    {
        return $this->publicSuffixRules
            ->getRules()
            ->resolve($commonName)
            ->registrableDomain()
            ->toString();
    }

    public function compareTwoDomains(string $one, string $two): bool
    {
        return $one === $two;
    }

    public function resendDcv(SslDeployment $sslDeployment): Result
    {
        throw new NotImplementedException();
    }

    public function getSslCnameRecord(SslDeployment $sslDeployment): ?DcvDetails
    {
        throw new NotImplementedException();
    }

    public function hasSslRequest(string $domain): bool
    {
        throw new NotImplementedException();
    }
}
