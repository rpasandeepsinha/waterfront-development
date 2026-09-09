<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Rules;

use Illuminate\Validation\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Ferry\Rules\RedirectSourceDomainIsPartOfSubscriptionRule;
use Waterfront\Infra\Common\PublicSuffixList;

#[CoversClass(RedirectSourceDomainIsPartOfSubscriptionRule::class)]
class RedirectSourceDomainIsPartOfSubscriptionRuleTest extends IntegrationTestCase
{
    #[Test]
    public function validateFailsWithoutMatchingDomainToSource(): void
    {
        $testDomain = 'test-redirect-domain.nl';
        $customer = CustomerFactory::new()->withAddress()->createOne();

        SubscriptionFactory::new()
            ->for($customer)
            ->forDomain($testDomain)
            ->for(ProductFactory::new()->nlDomain())
            ->create();

        $rule = new RedirectSourceDomainIsPartOfSubscriptionRule(
            $customer,
            $this->app->make(PublicSuffixList::class)
        );

        $validator = self::resolve(Factory::class);
        $validator = $validator->make([
            'source' => $testDomain,
        ], [
            'source' => [$rule],
        ]);

        self::assertFalse($validator->passes());
        self::assertSame('The source field with value: test-redirect-domain.nl has no representation as a redirect subscription domain: test-redirect-domain.nl', $validator->messages()->get('source')[0]);
    }

    #[Test]
    public function validatePasses(): void
    {
        $testDomain = 'test-redirect-domain.nl';
        $customer = CustomerFactory::new()->withAddress()->createOne();

        SubscriptionFactory::new()
            ->for($customer)
            ->forDomain($testDomain)
            ->for(ProductFactory::new()->nlDomain())
            ->create();

        SubscriptionFactory::new()
            ->for($customer)
            ->forDomain($testDomain)
            ->for(ProductFactory::new()->redirect())
            ->create();

        $rule = new RedirectSourceDomainIsPartOfSubscriptionRule(
            $customer,
            $this->app->make(PublicSuffixList::class)
        );

        $validator = self::resolve(Factory::class);
        $validator = $validator->make([
            'source' => $testDomain,
        ], [
            'source' => [$rule],
        ]);

        self::assertTrue($validator->passes());
    }
}
