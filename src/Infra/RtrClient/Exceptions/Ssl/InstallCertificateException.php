<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Exceptions\Ssl;

use Exception;
use Throwable;

class InstallCertificateException extends Exception
{
    public static function couldNotPrepareInstallParameters(int $sslDeploymentId, Throwable $previousException): self
    {
        return new self(
            sprintf(
                'Could not prepare install parameters for ssl deployment #%s: %s',
                $sslDeploymentId,
                $previousException->getMessage(),
            ),
            0,
            $previousException,
        );
    }

    public static function couldNotFindHostingSubscriptionForCertificate(
        int $sslDeploymentId,
        Throwable $previousException,
    ): self {
        return new self(
            sprintf(
                'Could not find hosting deployment for ssl deployment #%s: %s',
                $sslDeploymentId,
                $previousException->getMessage(),
            ),
            0,
            $previousException,
        );
    }

    public static function couldNotInstallCertificateOnHosting(int $sslDeploymentId, string $installResult): self
    {
        return new self(
            sprintf(
                'Could not install certificate on hosting server for ssl deployment #%s, install returned: %s',
                $sslDeploymentId,
                $installResult,
            ),
        );
    }
}
