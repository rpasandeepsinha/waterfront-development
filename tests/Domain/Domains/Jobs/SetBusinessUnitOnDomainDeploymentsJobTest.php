<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Jobs;

use Illuminate\Database\Eloquent\Factories\Sequence;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\DomainProviderBusinessUnitFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\Jobs\SetBusinessUnitOnDomainDeploymentsJob;
use Waterfront\Domain\Domains\Models\DomainDeployment;

#[CoversClass(SetBusinessUnitOnDomainDeploymentsJob::class)]
class SetBusinessUnitOnDomainDeploymentsJobTest extends IntegrationTestCase
{
    #[Test]
    public function correctUpdate(): void
    {
        $subscriptions = SubscriptionFactory::new()
            ->for(ProductFactory::new()->nlDomain())
            ->withCustomer()
            ->createMany(3)->pluck('uuid');

        $domainDeployments = DomainDeploymentFactory::new()
            ->for(ProviderFactory::new()->domainOpenProvider())
            ->state(
                new Sequence(
                    fn (Sequence $sequence) => [
                        'subscription_uuid' => $subscriptions->get($sequence->index),
                        'domain_business_unit_id' => null,
                    ],
                )
            )
            ->createMany(3);

        $businessUnit = DomainProviderBusinessUnitFactory::new()->argeweb()->createOne();
        $mockLogger = self::createMock(LoggerInterface::class);

        /** @var int[] $domainDeploymentIds */
        $domainDeploymentIds = $domainDeployments->pluck('id')->toArray();

        $mockLogger
            ->expects(self::once())
            ->method('debug')
            ->with(
                sprintf('Updating Business Unit to id %d on Domain Deployments', $businessUnit->id),
                self::callback(
                    fn (array $context) =>
                        $context['meta']['domain_deployment_ids'] === $domainDeploymentIds
                        && $context['meta']['business_unit_id'] === $businessUnit->id
                )
            );

        $job = new SetBusinessUnitOnDomainDeploymentsJob($businessUnit->id, $domainDeploymentIds);
        $job->handle($mockLogger);

        $domainDeployments->each(
            fn (DomainDeployment $domainDeployment) => self::assertSame(
                $businessUnit->id,
                $domainDeployment->refresh()->domain_business_unit_id
            )
        );
    }
}
