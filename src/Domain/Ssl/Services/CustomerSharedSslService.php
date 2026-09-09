<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\Console\Commands\Sanity\CheckSsl;
use Waterfront\Domain\Ssl\Clients\RemoteSslServiceClient;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Pipelines\CreateCertificate\GenerateCsrStep;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\RtrClient\DTO\DcvDetails;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class CustomerSharedSslService
{
    public function __construct(
        private readonly RemoteSslServiceClient $remoteSslServiceClient,
        private readonly CsrManager $csrManager,
        private readonly LoggerInterface $logger,
        private readonly GenerateCsrStep $generateCsrStep,
    ) {
    }

    public function create(int $period, SslDeployment $sslDeployment, ?string $csr): Result
    {
        if ($csr !== null) {
            $domain = $sslDeployment->subscription->domain;
            Assert::notNull($domain, 'Provided subscription has no domain');

            if ($this->validate($csr, $domain, $sslDeployment) === false) {
                $result = new Result();
                $result->setStatus(Result::STATUS_ERROR);
                $result->setErrorCode(422);
                $result->setErrorMessage('Failed validating csr for domain certificate');
                return $result;
            }
        }

        $result = $this->remoteSslServiceClient->create($period, $sslDeployment, $csr);

        /**
         * Override the interface which has a wrong datatype.
         *
         * @var string|null $certificateStatus
         */
        $certificateStatus = $result->getCertificateStatus();

        $sslDeployment->subscription->technical_status = $certificateStatus ?? $result->getStatus();
        $sslDeployment->subscription->save();

        return $result;
    }

    public function reissue(SslDeployment $sslDeployment): Result
    {
        $domain = $sslDeployment->subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        $hasSslRequest = $this->remoteSslServiceClient->hasSslRequest(sslDeployment: $sslDeployment);

        if ($hasSslRequest) {
            return Result::create([
                'status' => Result::STATUS_WAITING,
                'reason' => 'Certificate has request pending at RTR',
            ]);
        }

        $csrExistsAndCertificateActive = $this->checkCsrAndCertificate($sslDeployment, $domain);

        if (
            $csrExistsAndCertificateActive
        ) {
            $csr = $this->createCsr($sslDeployment, $domain);
            $result = $this->remoteSslServiceClient->reissue($sslDeployment, $csr);

            /**
             * Override the interface which has a wrong datatype.
             *
             * @var string|null $certificateStatus
             */
            $certificateStatus = $result->getCertificateStatus();

            $sslDeployment->subscription->technical_status = $certificateStatus ?? $result->getStatus();
            $sslDeployment->subscription->save();

            $sslDeployment->last_result_received = CarbonImmutable::now();
            $sslDeployment->last_result = json_encode([
                'reissue_result_status'        => $result->getStatus(),
                'reissue_result_error_code'    => $result->getErrorCode(),
                'reissue_result_error_message' => $result->getErrorMessage(),
                'reissue_result_reason'        => $result->getReason(),
            ], JSON_THROW_ON_ERROR);
            $sslDeployment->save();

            Artisan::call(CheckSsl::class, [
                'domain' => $domain,
            ]);

            $this->csrManager->saveCsr($domain, $csr);

            $sslDeployment->has_reissued = true;
            $sslDeployment->save();

            return $result;
        }

        /* Now that doing a reissue of the existing certificate is not possible,
         * we will order a new one.
         * To be able to do this we need to reset the request_id to NULL. */
        $sslDeployment->request_id = null;
        $sslDeployment->save();

        return $this->remoteSslServiceClient->create(
            $sslDeployment->subscription->contract_period,
            $sslDeployment
        );
    }

    public function retrieve(SslDeployment $sslDeployment): Result
    {
        return $this->remoteSslServiceClient->retrieve($sslDeployment);
    }

    /**
     * If we can find a local csr key for the subscription, then renew the certificate.
     * Otherwise, create a new certificate.
     */
    public function renew(SslDeployment $sslDeployment): Result
    {
        $domain = $sslDeployment->subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        $csrExistsAndCertificateActive = $this->checkCsrAndCertificate($sslDeployment, $domain);

        if (
            $csrExistsAndCertificateActive
        ) {
            return $this->remoteSslServiceClient->renew($sslDeployment);
        }

        /* Now that doing a renewal of the existing certificate is not possible,
         * we will order a new one.
         * To be able to do this we need to reset the request_id to NULL. */
        $sslDeployment->request_id = null;
        $sslDeployment->save();

        return $this->remoteSslServiceClient->create(
            $sslDeployment->subscription->contract_period,
            $sslDeployment
        );
    }

    /**
     * @return string[]|bool|Result
     */
    public function validate(string $csr, string $domain, SslDeployment $sslDeployment): array|bool|Result
    {
        return $this->remoteSslServiceClient->csrValidate($csr, $domain, $sslDeployment);
    }

    public function resendDcv(SslDeployment $sslDeployment): Result
    {
        if ($sslDeployment->subscription->technical_status === TechnicalStatus::OK->value) {
            return Result::create([
                'status' => Result::STATUS_ERROR,
                'errorCode' => Response::HTTP_CONFLICT,
                'errorMessage' => 'blocked',
                'reason' => 'Certificate already issued',
            ]);
        }

        return $this->remoteSslServiceClient->resendDcv($sslDeployment);
    }

    public function getDcvDetails(SslDeployment $sslDeployment): ?DcvDetails
    {
        return $this->remoteSslServiceClient->getDcvDetails($sslDeployment);
    }

    private function checkCsrAndCertificate(SslDeployment $sslDeployment, string $domain): bool
    {
        $remoteSslResult = null;

        try {
            $remoteSslResult = $this->retrieve($sslDeployment);
        } catch (LogicException $exception) {
            $this->logger->warning(
                sprintf('Unable to retrieve certificate: %s', $exception->getMessage()),
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'sslDeploymentId' => $sslDeployment->id,
                    ],
                ]
            );
        }

        $csrExists = $this->remoteSslServiceClient->csrExistsForDomain($domain, $sslDeployment->provider->slug);

        return $csrExists && $remoteSslResult !== null && $remoteSslResult->isCertificateActive();
    }

    private function createCsr(SslDeployment $sslDeployment, string $domain): string
    {
        $customerData = $sslDeployment->subscription->customer->load('address')->toArray();
        return $this->generateCsrStep->execute($customerData, $domain);
    }
}
