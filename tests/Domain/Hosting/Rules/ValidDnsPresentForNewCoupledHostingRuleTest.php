<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Rules\ValidDnsPresentForNewCoupledHostingRule;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(ValidDnsPresentForNewCoupledHostingRule::class)]
class ValidDnsPresentForNewCoupledHostingRuleTest extends IntegrationTestCase
{
    private const string DOMAIN = 'lorem.nl';

    /**
     * @return bool[][]
     */
    public static function dnsAllowsHostingDataProvider(): array
    {
        return [
            [true],
            [false],
        ];
    }

    #[DataProvider('dnsAllowsHostingDataProvider')]
    #[Test]
    public function withNewDomain(bool $dnsAllowsHosting): void
    {
        $dnsProduct = $this->buildDnsProduct($dnsAllowsHosting);

        $input = [
            'subscriptions' => [
                'extension' => [
                    [
                        'domain' => self::DOMAIN,
                        'children' => [
                            'dns' => [
                                [
                                    'slug' => $dnsProduct->slug,
                                ],
                            ],
                        ],
                    ],
                ],
                'hosting' => [
                    [
                        'domain' => self::DOMAIN,
                    ],
                ],
            ],
        ];

        $this->buildRuleForInput($input)->validate('domain', self::DOMAIN, self::assertClosureIsCalled(! $dnsAllowsHosting));
    }

    #[DataProvider('dnsAllowsHostingDataProvider')]
    #[Test]
    public function withExistingDomain(bool $dnsAllowsHosting): void
    {
        $dnsProduct = $this->buildDnsProduct($dnsAllowsHosting);

        SubscriptionFactory::new()
            ->withCustomer()
            ->for($dnsProduct)
            ->administrativeStatusActive()
            ->forDomain(self::DOMAIN)
            ->create();

        $input = [
            'subscriptions' => [
                'extension' => [],
                'hosting' => [
                    [
                        'domain' => self::DOMAIN,
                    ],
                ],
            ],
        ];

        $this->buildRuleForInput($input)->validate('domain', self::DOMAIN, self::assertClosureIsCalled(! $dnsAllowsHosting));
    }

    #[Test]
    public function marksInvalidIfDnsProductDoesNotExist(): void
    {
        $input = [
            'subscriptions' => [
                'extension' => [
                    [
                        'domain' => self::DOMAIN,
                        'children' => [
                            'dns' => [
                                [
                                    'slug' => 'does-not-exist',
                                ],
                            ],
                        ],
                    ],
                ],
                'hosting' => [
                    [
                        'domain' => self::DOMAIN,
                    ],
                ],
            ],
        ];

        $this->buildRuleForInput($input)->validate('domain', self::DOMAIN, self::assertClosureIsCalled(true));
    }

    private function buildDnsProduct(bool $allowsHostingCoupling): Product
    {
        $dnsProduct = ProductFactory::new()
            ->for(ProductGroupFactory::new()->dns())
            ->createOne();

        if ($allowsHostingCoupling) {
            ProductSpecFactory::new()
                ->for($dnsProduct)
                ->createOne([
                    'name' => ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT,
                    'value' => '1',
                ]);
        }

        return $dnsProduct;
    }

    /**
     * @param mixed[] $input
     */
    private function buildRuleForInput(array $input): ValidDnsPresentForNewCoupledHostingRule
    {
        return new ValidDnsPresentForNewCoupledHostingRule(
            $input,
            self::resolve(SubscriptionRepository::class),
            self::resolve(ProductRepository::class),
            self::resolve(ProductSpecRepository::class),
            self::resolve(TranslatorInterface::class),
        );
    }
}
