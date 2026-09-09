<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Cart\Validators\Rules\DomainIsUnclaimedRule;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\Pricing\Services\PricePersistService;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(DomainIsUnclaimedRule::class)]
class DomainIsUnclaimedRuleTest extends IntegrationTestCase
{
    #[Test]
    public function passesWithoutCustomerId(): void
    {
        $domain = 'test.nl';
        new CustomerFactory()->createOne();
        $group = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->createOne([
            'name' => '.nl',
            'slug' => 'extension_nl',
            'product_group_id' => $group->id,
        ]);

        $subscriptionRepository = new SubscriptionRepository(
            self::createStub(PriceResolver::class),
            self::createStub(PricePersistService::class),
            self::createStub(ConfigurationInterface::class),
            self::createStub(StoreNoteAction::class),
        );
        $rule = new DomainIsUnclaimedRule(
            $subscriptionRepository,
            self::createStub(TranslatorInterface::class),
        );

        $rule->validate('subscriptions.extension.*.domain', $domain, self::assertClosureIsCalled(false));

        new SubscriptionFactory()->withCustomer()->createOne([
            'product_uuid' => $product->uuid,
            'domain' => $domain,
        ]);

        $rule->validate('subscriptions.extension.*.domain', $domain, self::assertClosureIsCalled(true));
    }
}
