<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\SSL;

use Illuminate\Console\Attributes\Description;
use Illuminate\Contracts\Bus\Dispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Apps\Console\Commands\AbstractCommand;
use Waterfront\Domain\Ssl\Jobs\ReissueSslCertificate;
use Waterfront\Domain\Ssl\Repositories\DeploymentRepository as SslDeploymentRepository;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

#[AsCommand(name: 'ssl:reissue-expiring-ssl-certificates')]
#[Description('Queue daily to reissue SSL certificates that are in a 7 day window.')]
class ReissueExpiringSslCertificates extends AbstractCommand
{
    public const int EXPIRE_WINDOW = 7;
    public const int GRACE_EXPIRE_WINDOW = 7;

    public function __construct(
        private readonly SslDeploymentRepository $sslDeploymentRepository,
        private readonly Dispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $queued = 0;

        $expiringSslDeployments = $this->sslDeploymentRepository->getExpiringSslDeployments(
            days: self::EXPIRE_WINDOW,
            gracePeriodDays: self::GRACE_EXPIRE_WINDOW
        );

        $this->line(
            sprintf(
                '%d SSL certificates will expire in %d days or less',
                $expiringSslDeployments->count(),
                self::EXPIRE_WINDOW,
            )
        );

        foreach ($expiringSslDeployments as $expiringSslDeployment) {
            $expiringSslDeployment->subscription->technical_status = TechnicalStatus::PENDING->value;
            $expiringSslDeployment->subscription->save();

            $this->dispatcher->dispatch(new ReissueSslCertificate($expiringSslDeployment));
            $queued++;
        }

        $this->line(sprintf('SSL certificate reissues queued: %d', $queued));

        return self::SUCCESS;
    }
}
