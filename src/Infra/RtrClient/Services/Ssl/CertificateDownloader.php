<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Services\Ssl;

use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Enum\DownloadFormatEnum;
use RealtimeRegister\Exceptions\RealtimeRegisterClientException;
use RealtimeRegister\RealtimeRegister;
use Throwable;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CertificateManager;
use Waterfront\Infra\RtrClient\Exceptions\Ssl\DownloadCertificateException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class CertificateDownloader
{
    public function __construct(
        private readonly RealtimeRegister $realtimeRegister,
        private readonly CertificateManager $certificateManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws DownloadCertificateException
     */
    public function downloadForSslDeployment(SslDeployment $sslDeployment): void
    {
        $this->logger->debug(
            sprintf(
                'Downloading SSL RTR certificates #%d for SSL deployment #%d',
                $sslDeployment->certificate_id,
                $sslDeployment->id
            )
        );

        if ($sslDeployment->certificate_id === null) {
            throw DownloadCertificateException::certificateIdNotSet(
                $sslDeployment->id
            );
        }

        $cert = $this->downloadCertificate($sslDeployment, DownloadFormatEnum::CRT_FORMAT);
        $caBundle = $this->downloadCertificate($sslDeployment, DownloadFormatEnum::CA_BUNDLE_FORMAT);

        $this->saveCertificateFiles($sslDeployment, $cert, $caBundle);

        $this->logger->info(
            sprintf(
                'Downloaded and stored all certificate files for SSL deployment #%d',
                $sslDeployment->id
            )
        );
    }

    /**
     * @throws DownloadCertificateException
     */
    private function downloadCertificate(SslDeployment $sslDeployment, string $format): string
    {
        try {
            assert($sslDeployment->certificate_id !== null);
            $encodedData = $this->realtimeRegister->certificates->downloadCertificate($sslDeployment->certificate_id, $format);
            $decodedData = base64_decode($encodedData, true);
            assert($decodedData !== false);

            $this->logger->debug(
                sprintf(
                    'Downloaded the certificate (%s) for SSL deployment #%d',
                    $format,
                    $sslDeployment->id
                )
            );

            return $decodedData;
        } catch (RealtimeRegisterClientException $exception) {
            $this->logger->error(
                sprintf(
                    'Could not download RTR certificates #%s for SSL deployment #%s: %s',
                    $sslDeployment->certificate_id,
                    $sslDeployment->id,
                    $exception->getMessage()
                ),
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );

            throw DownloadCertificateException::couldNotDownloadCertificate(
                $sslDeployment->id,
                $sslDeployment->certificate_id,
                $format,
                $exception
            );
        }
    }

    /**
     * @throws DownloadCertificateException
     */
    private function saveCertificateFiles(SslDeployment $sslDeployment, string $cert, string $caBundle): void
    {
        try {
            $beginCertString = '-----BEGIN CERTIFICATE-----';
            $caBundleCerts = array_values(array_filter(explode($beginCertString, $caBundle)));
            Assert::greaterThanEq(count($caBundleCerts), 2, 'Unable to extract the intermediate and root certificate from the downloaded CA bundle.');

            $domain = $sslDeployment->subscription->domain;
            Assert::notNull($domain, 'Provided subscription has no domain');

            $this->certificateManager->saveMainCertificate(
                $domain,
                trim($cert)
            );
            $this->certificateManager->saveIntermediateCertificate(
                $domain,
                trim($beginCertString . $caBundleCerts[0])
            );
            $this->certificateManager->saveRootCertificate(
                $domain,
                trim($beginCertString . $caBundleCerts[1])
            );
        } catch (Throwable $exception) {
            $this->logger->error(
                sprintf(
                    'Could not store certificate for SSL deployment #%s: %s',
                    $sslDeployment->id,
                    $exception->getMessage()
                ),
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );

            throw DownloadCertificateException::couldNotStoreCertificate(
                $sslDeployment->id,
                $exception
            );
        }
    }
}
