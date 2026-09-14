<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;

class CertificateCloud
{
    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
    }

    public function storeCertificate(string $domain, string $certificate, string $type): void
    {
        $path = $this->getCertificatePath($domain, $type);

        $this->filesystem->put($path, $certificate);
    }

    public function getCertificate(string $domain, string $type): ?string
    {
        $path = $this->getCertificatePath($domain, $type);

        return $this->filesystem->exists($path) ? $this->filesystem->get($path) : null;
    }

    private function getCertificatePath(string $domain, string $type): string
    {
        return sprintf(
            '%s/%s.crt',
            $domain,
            $type,
        );
    }
}
