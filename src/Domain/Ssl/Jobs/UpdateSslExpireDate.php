<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Jobs;

use Illuminate\Container\Container;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CertificateService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class UpdateSslExpireDate extends AbstractQueueableJob
{
    public int $tries = 5;

    /** @var array<int> */
    public array $backoff = [5 * 60, 10 * 60, 30 * 60, 60 * 60];

    public function __construct(
        public readonly SslDeployment $sslDeployment,
        private readonly bool $fallbackToSubscriptionEndDate = false,
    ) {
        parent::__construct();
    }

    public function handle(
        CertificateService $certificateService,
    ): void {
        $certificateService->updateSslExpireDate($this->sslDeployment->refresh());
    }

    public function failed(?Throwable $exception): void
    {
        $logger = Container::getInstance()->make(LoggerInterface::class);
        $logger->warning(
            sprintf(
                'Could not update expire date for SSL deployment: %s%s',
                $this->sslDeployment->subscription->domain,
                $this->fallbackToSubscriptionEndDate ? ' (fallback to subscription end date)' : '',
            ),
            [
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SSL,
                LoggingContextKeys::PROVISIONING_PROVIDER => $this->sslDeployment->provider->slug,
                LoggingContextKeys::PROVISIONING_ID => $this->sslDeployment->id,
                LoggingContextKeys::DOMAIN_NAME => $this->sslDeployment->subscription->domain,
                LoggingContextKeys::EXCEPTION => $exception,
            ],
        );

        if ($this->fallbackToSubscriptionEndDate) {
            $this->sslDeployment->expire_date = $this->sslDeployment->subscription->end_date;
            $this->sslDeployment->save();
        }
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::PARTNER_SSL;
    }
}
