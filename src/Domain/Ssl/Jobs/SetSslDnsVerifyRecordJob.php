<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Jobs;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CsrManager;
use Waterfront\Domain\Ssl\ValueObjects\SslProduct;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\Services\RtrSslService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class SetSslDnsVerifyRecordJob extends AbstractQueueableJob
{
    public function __construct(
        private readonly SslDeployment $sslDeployment,
    ) {
        parent::__construct();
    }

    /**
     * @throws FileNotFoundException
     */
    public function handle(
        RtrSslService $rtrSslService,
        CsrManager $csrManager,
        LoggerInterface $logger,
    ): void {
        $subscription = $this->sslDeployment->subscription;
        $domain = $subscription->domain;

        if ($domain === null) {
            $logger->warning(
                'Set SSL DNS verify record: subscription has no domain; skipping',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                ]
            );

            return;
        }

        $sslDomain = $this->isWildcard($subscription) ? '*.' . $domain : $domain;

        $csr = $csrManager->getRawCsr($sslDomain);

        $rtrSslService->updateDns($domain, $csr);

        $logger->info(
            'Set SSL DNS verify record: updated DNS validation record',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::DOMAIN_NAME => $domain,
            ]
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::PARTNER_SSL;
    }

    private function isWildcard(Subscription $subscription): bool
    {
        /** @var ProductSpec $productSpec */
        $productSpec = $subscription->product->productSpecs()
            ->where('name', 'ssl.product_id')
            ->firstOrFail();

        return SslProduct::fromNative($productSpec->value)->isWildcardSsl();
    }
}
