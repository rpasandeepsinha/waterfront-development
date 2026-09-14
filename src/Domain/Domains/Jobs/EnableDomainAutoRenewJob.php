<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Jobs;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Exceptions\EnableAutorenewalFailedException;
use Waterfront\Domain\Email\Actions\SendEnableAutoRenewFailedMailAction;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class EnableDomainAutoRenewJob extends AbstractQueueableJob
{
    public function __construct(
        private readonly Subscription $subscription,
    ) {
        parent::__construct();
    }

    public function handle(
        DomainService $domainService,
        SendEnableAutoRenewFailedMailAction $enableAutoRenewFailedMailAction,
        LoggerInterface $logger,
    ): void {
        if ($this->subscription->domainDeployment === null) {
            $errorText = sprintf(
                'Subscription with uuid: %s does not have a domain subscription.',
                $this->subscription->uuid,
            );
            $logger->error($errorText);
            $this->fail($errorText);

            return;
        }

        $domain = $this->subscription->domain;

        if ($domain === null) {
            $errorText = sprintf(
                'subscription with uuid: %s does not have a domain.',
                $this->subscription->uuid,
            );
            $logger->error($errorText);
            $this->fail($errorText);

            return;
        }

        $providerSlug = $this->subscription->domainDeployment->provider->slug;

        try {
            $domainService->enableAutoRenewal($domain, $providerSlug);
        } catch (EnableAutorenewalFailedException $exception) {
            $logger->error(sprintf(
                'Enabling auto renew for domain %s with uuid %s failed. provider message: %s',
                $this->subscription->domain,
                $this->subscription->uuid,
                $exception->getMessage(),
            ));
            $this->subscription->technical_status = TechnicalStatus::ERROR->value;
            $this->subscription->save();
            $enableAutoRenewFailedMailAction->execute($this->subscription);

            $this->fail($exception);

            return;
        }

        $this->subscription->technical_status = TechnicalStatus::OK->value;
        $this->subscription->save();
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::PARTNER_DOMAIN;
    }
}
