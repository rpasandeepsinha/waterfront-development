<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Services;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use LogicException;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Enum\ProcessStatusEnum;
use RealtimeRegister\Domain\Process;
use RealtimeRegister\Domain\ProcessCollection;
use RealtimeRegister\Exceptions\BadRequestException;
use RealtimeRegister\RealtimeRegister;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Waterfront\Domain\DNS\Services\Ssl\SslDnsService;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;
use Waterfront\Domain\Ssl\Interfaces\SslDriverInterface;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Pipelines\CreateCertificate\GenerateCsrStep;
use Waterfront\Domain\Ssl\Services\CertificateManager;
use Waterfront\Domain\Ssl\Services\CertificateService;
use Waterfront\Domain\Ssl\Services\CsrManager;
use Waterfront\Domain\Ssl\Services\CsrValidationValueGenerator;
use Waterfront\Domain\Ssl\ValueObjects\SslProduct;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\RtrClient\DTO\DcvDetails;
use Waterfront\Infra\RtrClient\Services\Ssl\CertificateRequester;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;
use Webmozart\Assert\Assert;

class RtrSslService implements SslDriverInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly CertificateService $certificateService,
        private readonly CsrManager $csrManager,
        private readonly CertificateRequester $certificateRequester,
        private readonly GenerateCsrStep $generateCsrStep,
        private readonly CsrValidationValueGenerator $csrValidationValueGenerator,
        private readonly SslDnsService $sslDnsService,
        private readonly CertificateManager $certificateManager,
        private readonly PublicSuffixList $publicSuffixRules,
        private RealtimeRegister $realtimeRegister,
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws Exception
     */
    public function create(
        ProductSpec $productSpec,
        int $period,
        array $customerData,
        SslDeployment $sslDeployment,
        ?string $csr = null
    ): Result {
        $this->logger->info(
            self::class . '::create - Create new ssl',
            [
                LoggingContextKeys::META => [
                    'ssl_deployment_id' => $sslDeployment->id,
                ],
            ]
        );

        $domain = $sslDeployment->subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        $sslProduct = SslProduct::fromNative($productSpec->value);

        $sslDomain = $domain;
        if ($sslProduct->isWildcardSsl()) {
            $sslDomain = '*.' . $domain;
        }

        $hasCustomCsr = false;
        if ($csr !== null) {
            $this->csrManager->storeUserSupplied($csr, $domain);
            $hasCustomCsr = true;
        } else {
            $csr = $this->generateCsrStep->execute($customerData, $sslDomain);
        }

        $this->updateDns($domain, $csr);

        $product = $sslProduct->toNative();
        assert(is_string($product));

        $processId = $this->certificateRequester->request($customerData, $product, $period, $csr);

        $sslDeployment->update([
            'request_id' => $processId,
            'last_result_received' => CarbonImmutable::now(),
            'last_result' => json_encode([
                'process_id' => $processId,
                'certificate_status' => 'Certificate request is created. Pending validation.',
            ], JSON_THROW_ON_ERROR),
            'custom_csr' => $hasCustomCsr,
        ]);

        return Result::create([
            'requestId' => $processId,
            'status' => Result::STATUS_WAITING,
        ]);
    }

    public function hasSslRequest(string $domain): bool
    {
        $result = $this->realtimeRegister->processes->list(limit: 1, parameters: ['identifier' => $domain]);
        return count($result) > 0;
    }

    public function retrieve(SslDeployment $sslDeployment): Result
    {
        $this->logger->info(
            self::class . '::retrieve - Retrieve ssl',
            [
                LoggingContextKeys::META => [
                    'ssl_deployment_id' => $sslDeployment->id,
                ],
            ]
        );

        if ($sslDeployment->certificate_id === null) {
            throw new LogicException(
                "SSL deployment with id {$sslDeployment->id} has no certificate ID, so the SSL cannot be retrieved."
            );
        }

        return $this->certificateRequester->retrieve($sslDeployment->certificate_id);
    }

    /**
     * @inheritDoc
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

        $this->logger->info(
            self::class . '::reissue - Reissue ssl',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => [
                    'ssl_deployment_id' => $sslDeployment->id,
                ],
            ]
        );

        if ($sslDeployment->certificate_id === null) {
            throw new LogicException(
                'reissue certificate called, but no certificate set for SSL deployment with id:' . $sslDeployment->id
            );
        }

        $this->updateDns($domain, $csr);

        $processId = $this->certificateRequester->reissue(
            $sslDeployment->certificate_id,
            $customerData,
            $csr
        );

        $sslDeployment->update([
            'request_id' => $processId,
            'last_result' => json_encode([
                'process_id' => $processId,
                'certificate_status' => 'Certificate reissue has been requested. Pending validation.',
            ], JSON_THROW_ON_ERROR),
            'last_result_received' => CarbonImmutable::now(),
        ]);

        return Result::create([
            'status' => Result::STATUS_ISSUED,
            'requestId' => $processId,
        ]);
    }

    /**
     * @throws Exception
     */
    public function renew(SslDeployment $sslDeployment): Result
    {
        $domain = $sslDeployment->subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        $this->logger->info(
            self::class . '::renew - Renew ssl',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => [
                    'ssl_deployment_id' => $sslDeployment->id,
                ],
            ]
        );

        if ($sslDeployment->certificate_id === null) {
            throw new LogicException(
                'renew certificate called, but no certificate set for SSL deployment with id:' . $sslDeployment->id
            );
        }

        $customerData = $sslDeployment->subscription->customer->load('address')->toArray();
        $csr = $this->generateCsrStep->execute($customerData, $domain);

        $this->updateDns($domain, $csr);

        $processId = $this->certificateRequester->renew(
            $sslDeployment->certificate_id,
            $customerData,
            $sslDeployment->subscription->contract_period,
            $csr
        );

        $sslDeployment->update([
            'request_id' => $processId,
            'last_result_received' => CarbonImmutable::now(),
            'last_result' => json_encode([
                'process_id' => $processId,
                'certificate_status' => 'Certificate renewal has been requested. Pending validation.',
            ], JSON_THROW_ON_ERROR),
        ]);

        return Result::create([
            'requestId' => $processId,
            'status' => Result::STATUS_OK,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function check(string $domain = '', int $period = 12): bool
    {
        try {
            $status = $this->certificateService->check($domain, $period);
        } catch (Throwable $exception) {
            $this->logger->error(
                self::class . '::check - status code: ' . $exception->getCode()
                . ', message: ' . $exception->getMessage()
                . ', trace: ' . $exception->getTraceAsString()
            );

            throw $exception;
        }

        $this->logger->info(
            self::class . '::check - certificate validation',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => [
                    'status' => $status,
                ],
            ],
        );

        return $status;
    }

    /**
     * @inheritDoc
     */
    public function prepareCertificateInstallParameters(int $certificateId, string $domain, array $certificates, bool $alreadySaved = false): array
    {
        /** @var SslDeployment $sslDeployment */
        $sslDeployment = SslDeployment::query()
            ->where('certificated_id', '=', $certificateId)
            ->firstOrFail();

        $parameters = $this->certificateService->prepareCertificateInstallParameters($domain);

        return [
            'subscriptionUuid' => $sslDeployment->subscription_uuid,
            'parameters' => $parameters->toArray(),
        ];
    }

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

        if (
            ! Str::startsWith($csrTrimmed, '-----BEGIN CERTIFICATE REQUEST-----') ||
            ! Str::endsWith($csrTrimmed, '-----END CERTIFICATE REQUEST-----')
        ) {
            return false;
        }

        if (
            ! str_contains($csr, '-----BEGIN PRIVATE KEY-----')
            || ! str_contains($csr, '-----END PRIVATE KEY-----')
        ) {
            return false;
        }

        $csrArray = openssl_csr_get_subject($csr, false);
        if ($csrArray === false) {
            return false;
        }

        $csrDomain = $this->resolveCsrDomain($csrArray['commonName']);

        if ($sslDeployment !== null) {
            $domain = $sslDeployment->subscription->domain;
            Assert::notNull($domain, 'Provided subscription has no domain');
        }

        if ($this->compareTwoDomains($csrDomain, $domain)) {
            return $csrArray;
        }

        return false;
    }

    public function resendDcv(SslDeployment $sslDeployment): Result
    {
        $this->logger->info(self::class . '::resendDcv', [
            LoggingContextKeys::META => ['ssl_deployment_id' => $sslDeployment->id],
        ]);

        $domain = $sslDeployment->subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        $latestProcess = $this->getLatestProcess($domain);
        if (! $latestProcess instanceof Process) {
            return Result::create([
                'status' => Result::STATUS_ERROR,
                'errorCode' => Response::HTTP_PRECONDITION_FAILED,
                'errorMessage' => sprintf('No RTR certificate processes found for domain %s', $domain),
            ]);
        }

        if ($latestProcess->status === ProcessStatusEnum::STATUS_COMPLETED || $latestProcess->status === ProcessStatusEnum::STATUS_VALIDATED) {
            $sslDeployment->subscription->update(['technical_status' => TechnicalStatus::OK->value]);

            $sslDeployment->last_result_received = CarbonImmutable::now();
            $sslDeployment->last_result = json_encode([
                'action' => 'skipResendDcv',
                'reason' => 'completed',
                'processId' => $latestProcess->id,
            ], JSON_THROW_ON_ERROR);
            $sslDeployment->save();

            return Result::create([
                'status' => Result::STATUS_OK,
                'reason' => 'Latest process completed; technical status set to ok',
            ]);
        }

        if ($latestProcess->status === ProcessStatusEnum::STATUS_SUSPENDED) {
            $commonName = $this->resolveCommonName($domain);
            $resendDcvResponse = $this->certificateRequester->resendDcv($latestProcess->id, $commonName);

            $sslDeployment->last_result_received = CarbonImmutable::now();
            $sslDeployment->last_result = json_encode([
                'processId' => $latestProcess->id,
                'action' => 'resendDcv',
                'commonName' => $commonName,
                'result' => $resendDcvResponse->toArray(),
            ], JSON_THROW_ON_ERROR);
            $sslDeployment->save();

            return $resendDcvResponse;
        }

        $this->logger->warning(self::class . '::resendDcv - latest process has unknown status', [
            LoggingContextKeys::DOMAIN_NAME => $domain,
            LoggingContextKeys::META => [
                'sslDeploymentId' => $sslDeployment->id,
                'processId' => $latestProcess->id,
                'status' => $latestProcess->status,
            ],
        ]);

        $sslDeployment->last_result_received = CarbonImmutable::now();
        $sslDeployment->last_result = json_encode([
            'action' => 'skipResendDcv',
            'reason' => 'unknownStatus',
            'processId' => $latestProcess->id,
            'status' => $latestProcess->status,
        ], JSON_THROW_ON_ERROR);
        $sslDeployment->save();

        return Result::create([
            'status' => Result::STATUS_ERROR,
            'errorCode' => Response::HTTP_PRECONDITION_FAILED,
            'errorMessage' => sprintf('Unknown latest process status "%s"; no resend performed', $latestProcess->status),
        ]);
    }

    public function resolveCsrDomain(string $commonName): string
    {
        $domain = $this->publicSuffixRules
            ->getRules()
            ->resolve($commonName);

        return $domain->registrableDomain()->toString();
    }

    public function compareTwoDomains(string $one, string $two): bool
    {
        return $one === $two;
    }

    public function updateDns(string $domain, string $csr): void
    {
        $result = new Result();
        $result->setDnsRecord($this->csrValidationValueGenerator->getCnameValidationHost($csr) . '.' . $domain);
        $result->setDnsValue($this->csrValidationValueGenerator->getCnameValidationValue($csr));

        $registrableDomain = $this->publicSuffixRules
            ->getRules()
            ->resolve($domain);

        $this->sslDnsService->updateDns($result, $registrableDomain->registrableDomain()->toString());
    }

    public function prepareCertificates(int $certificateId, string $domain, array $certificates): void
    {
        throw new NotImplementedException();
    }

    /**
     * Check if private key matches certificate.
     */
    public function checkPrivateKeyMatches(string $domain): bool
    {
        $cert = $this->certificateManager->getMainCertificate($domain);

        if ($cert === null) {
            throw new RuntimeException('No certificate found for domain: ' . $domain);
        }

        try {
            $pkey = $this->csrManager->getPrivateKey($domain);
        } catch (FileNotFoundException) {
            $this->logger->info(self::class . 'checkPrivateKeyMatches - Private key not found for ' . $domain);

            return false;
        }

        return openssl_x509_check_private_key($cert, $pkey);
    }

    public function setClient(RealtimeRegister $realtimeRegister): RtrSslService
    {
        $this->realtimeRegister = $realtimeRegister;
        return $this;
    }

    public function getSslCnameRecord(SslDeployment $sslDeployment): ?DcvDetails
    {
        Assert::notNull($sslDeployment->subscription->domain);
        $latestProcess = $this->getLatestProcess($sslDeployment->subscription->domain);
        if (! $latestProcess instanceof Process) {
            return null;
        }

        if ($latestProcess->status === ProcessStatusEnum::STATUS_COMPLETED || $latestProcess->status === ProcessStatusEnum::STATUS_VALIDATED) {
            $sslDeployment->subscription->update(['technical_status' => TechnicalStatus::OK->value]);

            $sslDeployment->last_result_received = CarbonImmutable::now();
            $sslDeployment->last_result = json_encode([
                'action' => 'skipResendDcv',
                'reason' => 'completed',
                'processId' => $latestProcess->id,
            ], JSON_THROW_ON_ERROR);
            $sslDeployment->save();

            return null;
        }
        $processInfo = $this->realtimeRegister->processes->info($latestProcess->id)->toArray();

        $validations = Arr::get($processInfo, 'validations');
        if (! is_array($validations)) {
            return null;
        }

        $dcv = Arr::get($validations, 'dcv');
        if (! is_array($dcv) || count($dcv) === 0) {
            return null;
        }

        /** @var string[] $dcvFirst */
        $dcvFirst = array_first($dcv);

        return new DcvDetails(
            status: $dcvFirst['status'],
            caaRecordStatus: $dcvFirst['caaRecordStatus'],
            dnsRecord: $dcvFirst['dnsRecord'],
            dnsType: $dcvFirst['dnsType'],
            dnsContent: $dcvFirst['dnsContents'],
        );
    }

    public function getLatestSslProcess(string $domain): ProcessCollection
    {
        return $this->realtimeRegister->processes->list(
            limit: 1,
            parameters: [
                'identifier' => $domain,
                'type' => 'certificate',
                'order' => '-createdDate',
            ]
        );
    }

    private function getProcesses(string $domain): ?ProcessCollection
    {
        try {
            $processList = $this->realtimeRegister->processes->list(
                parameters: [
                    'identifier:eq' => $domain,
                    'type:eq' => 'certificate',
                ]
            );
            if (count($processList->entities) === 0) {
                return null;
            }
            return $processList;
        } catch (BadRequestException) {
            return null;
        }
    }

    private function getLatestProcess(string $domain): ?Process
    {
        $processList = $this->getProcesses($domain);
        if ($processList === null) {
            return null;
        }

        $last = end($processList->entities);
        return $last instanceof Process ? $last : null;
    }

    private function fallbackDomain(string $domain): string
    {
        return str_starts_with($domain, '*.') ? substr($domain, 2) : '*.' . $domain;
    }

    private function readCsrWithFallback(string $domain): ?string
    {
        if ($this->csrManager->hasCsr($domain)) {
            return $this->csrManager->getRawCsr($domain);
        }

        $fallback = $this->fallbackDomain($domain);
        if ($this->csrManager->hasCsr($fallback)) {
            return $this->csrManager->getRawCsr($fallback);
        }

        return null;
    }

    private function resolveCommonName(string $domain): string
    {
        $csr = $this->readCsrWithFallback($domain);
        if (! is_string($csr) || $csr === '') {
            return $domain;
        }

        try {
            return $this->certificateRequester->getCommonNameFromCsr($csr);
        } catch (InvalidArgumentException $exception) {
            $this->logger->debug(self::class . '::resolveCommonName - CSR parse failed', [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::META => [
                    'note' => 'Falling back to domain as csr commonName',
                ],
            ]);
            return $domain;
        }
    }
}
