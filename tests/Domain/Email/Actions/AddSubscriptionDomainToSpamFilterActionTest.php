<?php

declare(strict_types=1);

namespace Tests\Domain\Email\Actions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Factories\SpamExpertsClusterFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\TestCase;
use Waterfront\Domain\Email\Actions\AddSubscriptionDomainToSpamFilterAction;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Infra\SpamExpertsClient\SpamExpertsClient;

#[CoversClass(AddSubscriptionDomainToSpamFilterAction::class)]
class AddSubscriptionDomainToSpamFilterActionTest extends TestCase
{
    #[Test]
    public function executeAddsTheDomainToTheClusterOfTheHostingDeployment(): void
    {
        $spamExpertsCluster = new SpamExpertsClusterFactory()->makeOne();

        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->setRelation('spamExpertsCluster', $spamExpertsCluster);

        $subscription = new SubscriptionFactory()->makeOne(['domain' => 'sandwave.io']);
        $subscription->setRelation('hostingDeployment', $hostingDeployment);

        $spamExpertsClient = self::createMock(SpamExpertsClient::class);
        $spamExpertsClient->expects(self::once())->method('addDomain')->with('sandwave.io', $spamExpertsCluster);

        self::assertTrue(
            new AddSubscriptionDomainToSpamFilterAction($spamExpertsClient)->execute($subscription),
        );
    }

    #[Test]
    public function executeAddsTheDomainToTheDefaultClusterWhenThereIsNoHostingDeployment(): void
    {
        $subscription = new SubscriptionFactory()->makeOne(['domain' => 'sandwave.io']);
        $subscription->setRelation('hostingDeployment', null);

        $spamExpertsClient = self::createMock(SpamExpertsClient::class);
        $spamExpertsClient->expects(self::once())->method('addDomain')->with('sandwave.io', null);

        self::assertTrue(
            new AddSubscriptionDomainToSpamFilterAction($spamExpertsClient)->execute($subscription),
        );
    }

    #[Test]
    public function executeReturnsFalseWhenTheSubscriptionHasNoDomain(): void
    {
        $subscription = new SubscriptionFactory()->makeOne(['domain' => null]);

        $spamExpertsClient = self::createMock(SpamExpertsClient::class);
        $spamExpertsClient->expects(self::never())->method('addDomain');

        self::assertFalse(
            new AddSubscriptionDomainToSpamFilterAction($spamExpertsClient)->execute($subscription),
        );
    }

    #[Test]
    public function executeReturnsFalseWhenTheSpamExpertsClientFails(): void
    {
        $subscription = new SubscriptionFactory()->makeOne(['domain' => 'sandwave.io']);
        $subscription->setRelation('hostingDeployment', null);

        $spamExpertsClient = self::createMock(SpamExpertsClient::class);
        $spamExpertsClient
            ->expects(self::once())
            ->method('addDomain')
            ->willThrowException(new RuntimeException('Domain already exists'));

        self::assertFalse(
            new AddSubscriptionDomainToSpamFilterAction($spamExpertsClient)->execute($subscription),
        );
    }
}
