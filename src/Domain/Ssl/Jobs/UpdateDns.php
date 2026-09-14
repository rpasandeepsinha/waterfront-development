<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Jobs;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Waterfront\Domain\DNS\Services\Ssl\SslDnsService;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\OpenproviderClient\Factories\OpenproviderClientFactory;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

/**
 * Job for updating DNS when SSL certificate is active, retry if not.
 */
class UpdateDns extends AbstractQueueableJob
{
    public int $tries = 24 * 7;

    /**
     * Retry in one hour.
     *
     * @var int
     */
    private $retry = 3600;

    public function __construct(
        private readonly string $domain,
        private readonly bool $recursive = true,
    ) {
        parent::__construct();
    }

    /**
     * @throws Exception
     */
    public function handle(
        SslDnsService $sslDnsService,
        OpenproviderClientFactory $openproviderClientFactory,
        PublicSuffixList $rules,
    ): void {
        $subscription = Subscription::query()
            ->whereProductGroupType(ProductGroupType::SSL)
            ->where('domain', $this->domain)
            ->firstOrFail();

        $sslDeployment = $subscription->sslDeployment;

        if (is_null($sslDeployment)) {
            throw new RuntimeException(sprintf(
                'Subscription for domain %s has no SSL deployment',
                $this->domain,
            ));
        }

        /** @var int $certificateId */
        $certificateId = $sslDeployment->certificate_id;

        $result = $openproviderClientFactory->create()->retrieveSsl($certificateId);

        $sslDeployment->last_result_received = CarbonImmutable::now();
        $sslDeployment->last_result = json_encode($result->toArray(), JSON_THROW_ON_ERROR);
        $sslDeployment->save();

        switch ($result->getCertificateStatus()) {
            case DomainStatus::REQUESTED->value:
                // Retry in an hour
                Log::info(sprintf(
                    'Checking DNS response for SSL certificate %s with certificate id %s because certificate is still requested',
                    $this->domain,
                    $result->getCertificateId(),
                ));

                if ($result->getDnsRecord() !== '') {
                    $domain = $rules->getRegistrableDomain($result->getDnsRecord());
                    assert(! is_null($domain));

                    $sslDnsService->updateDns($result, $domain);

                    $this->delete();

                    return;
                }

                Log::info(sprintf(
                    'No DNS record found in SSL response for domain %s with certificate id %s',
                    $this->domain,
                    $result->getCertificateId(),
                ));

                $this->recursive ? $this->release($this->retry) : $this->delete();
                break;
            default:
                Log::info(sprintf(
                    'Deleted job to update DNS for SSL certificate %s with certificate id %s because certificate status is %s',
                    $this->domain,
                    $result->getCertificateId(),
                    $result->getCertificateStatus(),
                ));

                $this->delete();
                break;
        }
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::PARTNER_SSL;
    }
}
