<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Jobs;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;
use Webmozart\Assert\Assert;

class DecouplePrimaryDomainJob extends AbstractQueueableJob
{
    private const string MICROSOFT_SUBDOMAIN = '.onmicrosoft.com';

    public int $tries = 7;

    public function __construct(
        private readonly Microsoft365CustomerInfo $customerInfo,
    ) {
        parent::__construct();
    }

    /**
     * @return int[]
     */
    public function backoff(): array
    {
        return [
            60,
            5 * 60,
            10 * 60,
            30 * 60,
            60 * 60,
            2 * 60 * 60,
        ];
    }

    public function failed(?Throwable $throwable): void
    {
        $container = Container::getInstance();
        $logger = $container->make(LoggerInterface::class);
        $logger->error(
            'Error DecouplePrimaryDomainJob for domain {domain.name} job definitely failed after {queue.attempt} attempts',
            [
                LoggingContextKeys::DOMAIN_NAME => $this->customerInfo->primary_domain,
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::EXCEPTION => $throwable,
                LoggingContextKeys::META => [
                    'Microsoft365CustomerInfoId' => $this->customerInfo->id,
                ],
            ],
        );

        Subscription::query()
            ->whereHas(
                'microsoft365Customer',
                fn (Builder $query) => $query->where('customer_id', $this->customerInfo->customer_id)->where(
                    'tenant_name',
                    $this->customerInfo->tenant_name,
                ),
            )
            ->update(['technical_status' => TechnicalStatus::FAILED->value]);
    }

    public function handle(
        Microsoft365Service $microsoft365Service,
        LoggerInterface $logger,
    ): void {
        Assert::string($this->customerInfo->tenant_name);
        $tenantId = $this->customerInfo->tenant_id;
        $domain = $this->customerInfo->primary_domain;
        $tenantName = $this->formatTenantName($this->customerInfo->tenant_name);
        Assert::string($tenantId);
        Assert::string($domain);

        $subscription = $this->customerInfo->microsoft365Deployments->first()?->subscription;
        Assert::notNull(
            $subscription,
            'Microsoft365CustomerInfo must have at least one deployment with a subscription.',
        );

        $logger->debug(
            sprintf(
                'Promoting [{domain.name}] to primary domain, attempt {queue.attempt}/%d',
                $this->tries,
            ),
            [
                LoggingContextKeys::DOMAIN_NAME => $tenantName,
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::META => [
                    'Microsoft365CustomerInfoId' => $this->customerInfo->id,
                ],
            ],
        );

        $promoted = $microsoft365Service->promoteDomainInMicrosoftAccount(
            domain: $tenantName,
            tenantId: $tenantId,
            subscription: $subscription,
        );
        if (! $promoted) {
            $logger->error(
                'Promoting of [{domain.name}] to primary domain failed',
                [
                    LoggingContextKeys::DOMAIN_NAME => $tenantName,
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::META => [
                        'Microsoft365CustomerInfoId' => $this->customerInfo->id,
                    ],
                ],
            );
            $this->release($this->getBackoffDelay());

            return;
        }

        $logger->debug(
            sprintf(
                'Removing [{domain.name}] domain from tenant, attempt {queue.attempt}/%d',
                $this->tries,
            ),
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::META => [
                    'Microsoft365CustomerInfoId' => $this->customerInfo->id,
                ],
            ],
        );

        $deleted = $microsoft365Service->deleteDomainInMicrosoftAccount(
            domain: $domain,
            tenantId: $tenantId,
            subscription: $subscription,
        );

        if (! $deleted) {
            $logger->error(
                'Removal of [{domain.name}] domain from tenant failed.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::META => [
                        'Microsoft365CustomerInfoId' => $this->customerInfo->id,
                    ],
                ],
            );
            $this->release($this->getBackoffDelay());

            return;
        }

        $this->customerInfo->primary_domain = null;
        $this->customerInfo->primary_domain_status = null;
        $this->customerInfo->save();
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::MICROSOFT365;
    }

    private function formatTenantName(string $tenantName): string
    {
        if (! str_ends_with($tenantName, self::MICROSOFT_SUBDOMAIN)) {
            $tenantName .= self::MICROSOFT_SUBDOMAIN;
        }

        return $tenantName;
    }
}
