<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Container\Container;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Interfaces\DomainDriverInterface;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\Services\Enums\DomainStatus as RtrDomainStatus;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;
use Webmozart\Assert\Assert;

class UpdateDomainNameRegistrationJob extends AbstractQueueableJob
{
    public int $tries = 7;

    private readonly Subscription $domainSubscription;

    /**
     * @param Nameserver[] $nameservers
     */
    public function __construct(
        private readonly DomainDeployment $domainDeployment,
        private readonly array $nameservers,
    ) {
        $this->domainDeployment->loadMissing('subscription.product');
        $this->domainSubscription = $this->domainDeployment->subscription;

        parent::__construct();
    }

    public function failed(?Throwable $throwable): void
    {
        $container = Container::getInstance();
        $logger = $container->make(LoggerInterface::class);
        $logger->error(
            'Error UpdateDomainRegistrationJob for domain {domain.name} job definitely failed after {job.attempt} attempts',
            [
                LoggingContextKeys::DOMAIN_NAME => $this->domainSubscription->domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->domainSubscription->uuid,
                LoggingContextKeys::SUBSCRIPTION_ID => $this->domainSubscription->id,
                LoggingContextKeys::EXCEPTION => $throwable,
                LoggingContextKeys::PROVISIONING_TYPE => 'domain',
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
            ],
        );

        $this->domainSubscription->update([
            'technical_status' => TechnicalStatus::FAILED->value,
        ]);

        $this->domainDeployment->update([
            'last_result_received' => CarbonImmutable::now(),
            'last_result' => json_encode([
                'message' => 'Domain is registered but failed to update nameservers, dnssec, and private whois',
                'exception' => $throwable?->getMessage(),
                'trace' => $throwable?->getTraceAsString(),
            ]),
        ]);
    }

    public function handle(
        DomainServiceFactory $domainServiceFactory,
        LoggerInterface $logger,
    ): void {
        $domainName = $this->domainSubscription->domain;
        Assert::notNull($domainName);

        $logger->info('UpdateDomainRegistrationJob started for domain {domain.name}', [
            LoggingContextKeys::DOMAIN_NAME => $domainName,
            LoggingContextKeys::SUBSCRIPTION_UUID => $this->domainSubscription->uuid,
            LoggingContextKeys::SUBSCRIPTION_ID => $this->domainSubscription->id,
            LoggingContextKeys::PROVISIONING_TYPE => 'domain',
        ]);

        $domainDriver = $domainServiceFactory->driver(
            $this->domainDeployment->provider->slug,
            $this->domainDeployment->businessUnit,
        );

        $dnssecKeyData = $this->domainDeployment->dnssec_enabled
            ? $domainDriver->getDomainKeyDataCollection($domainName)
            : null;

        $nameserverHostnames = array_map(fn (Nameserver $nameserver) => $nameserver->hostname, $this->nameservers);

        $domainDriver->modify($domainName, [
            'dnssecKeys' => $dnssecKeyData,
            'ns' => $nameserverHostnames,
        ]);

        $message = sprintf('Successfully updated domain name registration for domain %s', $domainName);

        $this->domainDeployment->last_result_received = CarbonImmutable::now();
        $this->domainDeployment->last_result = json_encode([
            'message' => $message,
            'data' => [
                'dnssec' => $this->domainDeployment->dnssec_enabled,
                'ns' => $nameserverHostnames,
            ],
        ], JSON_THROW_ON_ERROR);
        $this->domainDeployment->save();

        $this->refreshDomainStatus($domainDriver, $domainName);
        $this->domainDeployment->refresh();
        $this->domainSubscription->refresh();

        if ($this->domainStatusAllowsActivation($domainDriver) && $this->technicalStatusAllowsActivation()) {
            $this->domainSubscription->technical_status = DomainStatus::ACTIVE->value;
            $this->domainSubscription->save();
        }
    }

    /**
     * @return int[]
     */
    public function backoff(): array
    {
        return [5, 30, 60, 5 * 60, 15 * 60, 60 * 60];
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::PARTNER_DOMAIN;
    }

    private function refreshDomainStatus(DomainDriverInterface $domainDriver, string $domainName): void
    {
        if (! $domainDriver instanceof RtrService) {
            return;
        }

        $remoteDomain = $domainDriver->fetchDomain($domainName);
        $domainStatus = $domainDriver->getPrimaryDomainStatusFromDomainStatusList($remoteDomain->status);

        if ($domainStatus === null) {
            return;
        }

        $this->domainDeployment->domain_status = $domainStatus;
        $this->domainDeployment->save();
    }

    /**
     * Domain subscriptions currently use both technical statuses and domain-specific statuses.
     * Only update-ready statuses should become ACT after a successful nameserver/DNSSEC modify.
     */
    private function domainStatusAllowsActivation(DomainDriverInterface $domainDriver): bool
    {
        if ($domainDriver instanceof RtrService) {
            return in_array(
                $this->domainDeployment->domain_status,
                [
                    RtrDomainStatus::OK,
                    RtrDomainStatus::INACTIVE,
                ],
                true,
            );
        }

        return (
            $this->domainDeployment->domain_status === null
            || in_array(
                $this->domainDeployment->domain_status,
                [
                    RtrDomainStatus::OK,
                    RtrDomainStatus::INACTIVE,
                    RtrDomainStatus::PENDING_VALIDATION,
                ],
                true,
            )
        );
    }

    private function technicalStatusAllowsActivation(): bool
    {
        return in_array(
            $this->domainSubscription->technical_status,
            [
                TechnicalStatus::PENDING->value,
                TechnicalStatus::OK->value,
                DomainStatus::PENDING->value,
                DomainStatus::ACTIVE->value,
            ],
            true,
        );
    }
}
