<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Facades\Storage;
use Waterfront\Domain\Ssl\CsrPersistanceStrategies\OpenSslExtensionStrategy;
use Waterfront\Domain\Ssl\Services\CertificateManager;
use Waterfront\Domain\Ssl\Services\CsrManager;
use Waterfront\Domain\Ssl\Storage\CertificateCloud;
use Waterfront\Domain\Ssl\Storage\KeyCloud;
use Waterfront\Domain\Ssl\Storage\LocalDisk;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Providers\BaseProvider;

class SslServiceProvider extends BaseProvider implements DeferrableProvider
{
    public function register(): void
    {
        $configuration = self::resolve(ConfigurationInterface::class);
        $cloudFilesystem = Storage::disk($configuration->getAsString('filesystems.cloud'));

        $this->app->bind(function () use ($configuration, $cloudFilesystem): CsrManager {
            $strategy = $this->resolve(OpenSslExtensionStrategy::class);

            $sslDisk = new KeyCloud(
                $configuration->getAsString('app.ssl_crypto'),
                $cloudFilesystem,
            );
            $localDisk = new LocalDisk($configuration->getAsString('filesystems.disks.private.csr'));

            return new CsrManager($strategy, $sslDisk, $localDisk);
        });

        $this->app->bind(fn (): CertificateCloud => new CertificateCloud($cloudFilesystem));
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            CsrManager::class,
            CertificateManager::class,
        ];
    }
}
