<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Subscriptions\Actions;

use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaRemoveDnsZoneAction;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(NovaRemoveDnsZoneAction::class)]
class NovaRemoveDnsZoneActionTest extends IntegrationTestCase
{
    public const string DOMAIN = 'test-remove-zone.nl';

    #[Test]
    public function removingDnsZoneFromDomainDeployment(): void
    {
        $mockDnsService = self::createMock(DnsService::class);

        $domainSubscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->extension()))
            ->createOne(
                [
                    'administrative_status' => AdministrativeStatus::ACTIVE->value,
                    'technical_status' => TechnicalStatus::OK->value,
                    'domain' => self::DOMAIN,
                ],
            );

        new DomainDeploymentFactory()
            ->withRtrProvider()
            ->for($domainSubscription, 'subscription')
            ->createOne();
        $action = new NovaRemoveDnsZoneAction(
            translator: self::createStub(TranslatorInterface::class),
            dnsService: $mockDnsService,
        );

        $mockDnsService->expects(self::once())->method('deleteZone')->with(self::DOMAIN);

        $subscriptions = new Collection([$domainSubscription]);

        $action->handle(
            new ActionFields(new Collection(), new Collection()),
            $subscriptions,
        );
    }

    #[Test]
    public function removingDnsZoneFailsForNonExtensionOrDns(): void
    {
        $mockDnsService = self::createMock(DnsService::class);

        $domainSubscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting()))
            ->createOne(
                [
                    'administrative_status' => AdministrativeStatus::ACTIVE->value,
                    'technical_status' => TechnicalStatus::OK->value,
                    'domain' => self::DOMAIN,
                ],
            );

        $action = new NovaRemoveDnsZoneAction(
            translator: self::createStub(TranslatorInterface::class),
            dnsService: $mockDnsService,
        );

        $mockDnsService->expects(self::never())->method('deleteZone');

        $subscriptions = new Collection([$domainSubscription]);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessageIs('Only allowed with a domain or DNS subscription');

        $action->handle(
            new ActionFields(new Collection(), new Collection()),
            $subscriptions,
        );
    }

    #[Test]
    public function removingDnsZoneFromDnsDeployment(): void
    {
        $mockDnsService = self::createMock(DnsService::class);

        $domainSubscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->dns())->premiumDns())
            ->createOne(
                [
                    'administrative_status' => AdministrativeStatus::ACTIVE->value,
                    'technical_status' => TechnicalStatus::OK->value,
                    'domain' => self::DOMAIN,
                ],
            );

        $action = new NovaRemoveDnsZoneAction(
            translator: self::createStub(TranslatorInterface::class),
            dnsService: $mockDnsService,
        );

        $mockDnsService->expects(self::once())->method('deleteZone')->with(self::DOMAIN);

        $subscriptions = new Collection([$domainSubscription]);

        $action->handle(
            new ActionFields(new Collection(), new Collection()),
            $subscriptions,
        );
    }
}
