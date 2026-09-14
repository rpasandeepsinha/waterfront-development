<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Services\Ssl;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Ssl\Interfaces\SslInstallServiceInterface;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CertificateInstaller;
use Waterfront\Infra\RtrClient\Exceptions\Ssl\InstallCertificateException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class SslInstallService implements SslInstallServiceInterface
{
    public function __construct(
        private readonly CertificateInstaller $certificateInstaller,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function installCertificate(array $data): string
    {
        try {
            Assert::keyExists($data, 'sslDeployment');
            $sslDeployment = $data['sslDeployment'];
            Assert::object($sslDeployment);
            Assert::isInstanceOf($sslDeployment, SslDeployment::class);
            /** @var SslDeployment $sslDeployment */
            $this->certificateInstaller->installForSslDeployment($sslDeployment);
        } catch (InstallCertificateException $exception) {
            $this->logger->error(
                sprintf('Could not install certificate: %s', $exception->getMessage()),
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return 'error';
        }

        return 'ok';
    }
}
