<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\Domain\Factories;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RealtimeRegister\Domain\ProcessCollection;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\OneOffScripts\Domain\DTO\FailedDomainSubscriptionRepair;
use Waterfront\Apps\OneOffScripts\Domain\Enum\FailedDomainSubscriptionRepairPath;
use Waterfront\Apps\OneOffScripts\Domain\Factories\FailedDomainSubscriptionRepairJobFactory;
use Waterfront\Apps\OneOffScripts\Domain\Jobs\RepairMissingRemoteNameserversJob;
use Waterfront\Apps\OneOffScripts\Domain\Jobs\RestoreFromProcesses;
use Waterfront\Apps\OneOffScripts\Domain\Jobs\RetryDomainProvisioningJob;
use Waterfront\Apps\OneOffScripts\Domain\Jobs\SyncDomainSubscriptionStatusFromRemoteJob;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(FailedDomainSubscriptionRepairJobFactory::class)]
class FailedDomainSubscriptionRepairJobFactoryTest extends IntegrationTestCase
{
    private const string TRIGGERED_BY = 'fix-failed-domain-subscriptions';

    private FailedDomainSubscriptionRepairJobFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new FailedDomainSubscriptionRepairJobFactory();
    }

    #[Test]
    public function makeReturnsRepairMissingRemoteNameserversJob(): void
    {
        $subscription = $this->createSubscription(
            subscriptionUuid: 'sub-uuid-101',
            domain: 'repair-missing-remote-ns.test',
        );

        $repairPlan = new FailedDomainSubscriptionRepair(
            path: FailedDomainSubscriptionRepairPath::REPAIR_NAMESERVERS,
            reason: 'remote_status_not_healthy_and_no_nameservers_set',
        );

        $actual = $this->factory->make(
            repairPlan: $repairPlan,
            subscription: $subscription,
            dryRun: true,
            triggeredBy: self::TRIGGERED_BY,
        );

        self::assertEquals(
            new RepairMissingRemoteNameserversJob(
                subscription: $subscription,
                dryRun: true,
                triggeredBy: self::TRIGGERED_BY,
            ),
            $actual,
        );
    }

    #[Test]
    public function makeReturnsRestoreFromProcessJob(): void
    {
        $subscription = $this->createSubscription(
            subscriptionUuid: 'sub-uuid-102',
            domain: 'restore-pending.test',
        );

        $processCollection = ProcessCollection::fromArray([]);

        $repairPlan = new FailedDomainSubscriptionRepair(
            path: FailedDomainSubscriptionRepairPath::RESTORE_FROM_PROCESS,
            processCollection: $processCollection,
            reason: 'remote_processes_found_no_domain',
        );

        $actual = $this->factory->make(
            repairPlan: $repairPlan,
            subscription: $subscription,
            dryRun: false,
            triggeredBy: self::TRIGGERED_BY,
        );

        self::assertEquals(
            new RestoreFromProcesses(
                processCollection: $processCollection,
                subscription: $subscription,
                dryRun: false,
                triggeredBy: self::TRIGGERED_BY,
            ),
            $actual,
        );
    }

    #[Test]
    public function makeReturnsRetryDomainProvisioningJob(): void
    {
        $subscription = $this->createSubscription(
            subscriptionUuid: 'sub-uuid-103',
            domain: 'retry-provisioning.test',
        );

        $repairPlan = new FailedDomainSubscriptionRepair(
            path: FailedDomainSubscriptionRepairPath::RETRY_PROVISIONING,
            reason: 'remote_domain_missing',
        );

        $actual = $this->factory->make(
            repairPlan: $repairPlan,
            subscription: $subscription,
            dryRun: false,
            triggeredBy: self::TRIGGERED_BY,
        );

        self::assertEquals(
            new RetryDomainProvisioningJob(
                subscription: $subscription,
                dryRun: false,
                triggeredBy: self::TRIGGERED_BY,
            ),
            $actual,
        );
    }

    #[Test]
    public function makeReturnsSyncDomainSubscriptionStatusFromRemoteJob(): void
    {
        $subscription = $this->createSubscription(
            subscriptionUuid: 'sub-uuid-104',
            domain: 'sync-status-only.test',
        );

        $repairPlan = new FailedDomainSubscriptionRepair(
            path: FailedDomainSubscriptionRepairPath::SYNC_STATUS_ONLY,
            reason: 'remote_status_is_ok',
        );

        $actual = $this->factory->make(
            repairPlan: $repairPlan,
            subscription: $subscription,
            dryRun: true,
            triggeredBy: self::TRIGGERED_BY,
        );

        self::assertEquals(
            new SyncDomainSubscriptionStatusFromRemoteJob(
                subscription: $subscription,
                dryRun: true,
                triggeredBy: self::TRIGGERED_BY,
            ),
            $actual,
        );
    }

    #[Test]
    public function makeReturnsNullForSkipPath(): void
    {
        $subscription = $this->createSubscription(
            subscriptionUuid: 'sub-uuid-105',
            domain: 'skip.test',
        );

        $repairPlan = new FailedDomainSubscriptionRepair(
            path: FailedDomainSubscriptionRepairPath::SKIP,
            reason: 'missing_dns_deployment',
        );

        $actual = $this->factory->make(
            repairPlan: $repairPlan,
            subscription: $subscription,
            dryRun: true,
            triggeredBy: self::TRIGGERED_BY,
        );

        self::assertNull($actual);
    }

    private function createSubscription(
        string $subscriptionUuid,
        string $domain,
    ): Subscription {
        $product = ProductFactory::new()
            ->nlDomain()
            ->createOne();

        $domainDeployment = DomainDeploymentFactory::new()
            ->withSubscription($product, [
                'uuid' => $subscriptionUuid,
                'domain' => $domain,
            ])
            ->withRtrProvider()
            ->createOne();

        $domainDeployment->subscription->setRelation('domainDeployment', $domainDeployment);
        $domainDeployment->setRelation('subscription', $domainDeployment->subscription);

        return $domainDeployment->subscription;
    }
}
