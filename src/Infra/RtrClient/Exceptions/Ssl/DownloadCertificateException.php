<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Exceptions\Ssl;

use Exception;
use Throwable;

class DownloadCertificateException extends Exception
{
    public static function certificateIdNotSet(int $sslDeploymentId): self
    {
        return new self(
            sprintf(
                'Cannot download certificate without certificate id on SSL deployment #%s',
                $sslDeploymentId,
            ),
        );
    }

    public static function couldNotDownloadCertificate(
        int $sslDeploymentId,
        int $rtrCertificateId,
        string $format,
        Throwable $previousException,
    ): self {
        return new self(
            sprintf(
                'Download certificate #%s failed for ssl deployment #%s in format %s: %s',
                $rtrCertificateId,
                $sslDeploymentId,
                $format,
                $previousException->getMessage(),
            ),
            0,
            $previousException,
        );
    }

    public static function couldNotStoreCertificate(int $sslDeploymentId, Throwable $previousException): self
    {
        return new self(
            sprintf(
                'Could not store certificate #%s: %s',
                $sslDeploymentId,
                $previousException->getMessage(),
            ),
            0,
            $previousException,
        );
    }
}
