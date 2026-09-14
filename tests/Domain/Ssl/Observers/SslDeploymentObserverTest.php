<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl\Observers;

use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\SslDeploymentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ssl\Jobs\UpdateSslExpireDate;
use Waterfront\Domain\Ssl\Observers\SslDeploymentObserver;

#[CoversClass(SslDeploymentObserver::class)]
class SslDeploymentObserverTest extends IntegrationTestCase
{
    #[Test]
    public function updateCertificateIdDispatchesUpdateSslExpireDateJob(): void
    {
        $sslDeployment = new SslDeploymentFactory()->rtrProvider()->createOneQuietly();

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                new UpdateSslExpireDate(
                    sslDeployment: $sslDeployment,
                ),
            );
        $this->app->bind(Dispatcher::class, fn (): Dispatcher => $dispatcher);

        $sslDeployment->certificate_id = 1337;
        $sslDeployment->save();
    }
}
