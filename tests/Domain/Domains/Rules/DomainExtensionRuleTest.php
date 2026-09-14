<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Rules;

use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Cart\Validators\Rules\DomainExtensionRule;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Interfaces\DomainDriverInterface;
use Waterfront\Domain\Domains\Services\PremiumDomainService;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(DomainExtensionRule::class)]
class DomainExtensionRuleTest extends IntegrationTestCase
{
    private PremiumDomainService $premiumDomainService;

    protected function setUp(): void
    {
        parent::setUp();

        $mailer = self::createStub(MailerInterface::class);
        $this->app->bind(MailerInterface::class, fn () => $mailer);

        $this->premiumDomainService = new PremiumDomainService($mailer, $this->getConfiguration());
    }

    /**
     * @param array<mixed> $value
     */
    #[DataProvider('passesProvider')]
    #[Test]
    public function passes(
        bool $expectedResult,
        array $value,
        bool $shouldCallFindBySlug,
        bool $shouldCheckPremium,
        bool $isPremium,
    ): void {
        /** @var string $domain */
        $domain = $value['domain'];

        /** @var string $slug */
        $slug = $value['slug'];

        $premiumDomainName = $isPremium ? $domain : null;

        $productRepository = $this->createProductRepository($slug, $shouldCallFindBySlug, $premiumDomainName);

        $domainDriverFactory = $this->createDomainService($domain, $shouldCheckPremium, $isPremium);

        $rule = new DomainExtensionRule(
            $productRepository,
            $this->premiumDomainService,
            $domainDriverFactory,
            self::createStub(TranslatorInterface::class),
        );

        $rule->validate('subscriptions.extension.*', $value, self::assertClosureIsCalled(! $expectedResult));
    }

    /**
     * @return array<mixed>
     */
    public static function passesProvider(): array
    {
        return [
            [
                true,
                ['slug' => 'extension_nl', 'domain' => 'ketchup.nl'],
                true,
                true,
                false,
            ],
            [
                false,
                ['slug' => 'extension_nl', 'domain' => 'ketchup.com'],
                true,
                true,
                false,
            ],
            [
                false,
                ['slug' => 'extension_nl', 'domain' => ''],
                false,
                false,
                false,
            ],
            [
                false,
                ['slug' => '', 'domain' => 'ketchup.com'],
                false,
                false,
                false,
            ],
            [
                false,
                ['slug' => 'extension_cars', 'domain' => 'fast.cars'],
                true,
                true,
                true,
            ],
            [
                true,
                ['slug' => 'extension_premium_katten_club', 'domain' => 'katten.club'],
                true,
                true,
                true,
            ],
            [
                false,
                ['slug' => 'extension_uk', 'domain' => 'ketchup.co.uk'],
                true,
                true,
                false,
            ],
            [
                true,
                ['slug' => 'extension_co.uk', 'domain' => 'ketchup.co.uk'],
                true,
                true,
                false,
            ],
        ];
    }

    private function createProductRepository(
        string $slug,
        bool $shouldCallFindBySlug,
        ?string $premiumDomainName,
    ): ProductRepository {
        $repository = $this->createMock(ProductRepository::class);

        if (! $shouldCallFindBySlug) {
            $repository->expects(self::never())->method('findProductBySlug');

            return $repository;
        }

        if ($premiumDomainName !== null) {
            $product = $this->createPremiumProduct($slug, $premiumDomainName);
        } else {
            $product = $this->createProduct($slug);
        }

        $repository->expects(self::once())->method('findProductBySlug')->willReturn($product);

        return $repository;
    }

    private function createProduct(string $slug): Product
    {
        [, $tld] = explode('_', $slug, 2);

        return new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne())->createOne([
            'name' => '.' . $tld,
            'slug' => $slug,
        ]);
    }

    private function createPremiumProduct(string $slug, string $premiumDomainName): Product
    {
        return new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne())->createOne([
            'name' => $this->premiumDomainService->getPremiumDomainProductName($premiumDomainName),
            'slug' => $slug,
        ]);
    }

    private function createDomainService(
        string $domain,
        bool $shouldCheckPremium,
        bool $isPremium,
    ): DomainServiceFactory&MockInterface {
        if (! $shouldCheckPremium) {
            $domainService = $this->mock(DomainDriverInterface::class);
            $domainService->shouldNotHaveBeenCalled();

            $domainDriverFactory = $this->mock(DomainServiceFactory::class);
            $domainDriverFactory->shouldNotHaveBeenCalled();

            return $domainDriverFactory;
        }

        $checkResult = new CheckResult(
            domain: $domain,
            status: CheckResult::STATUS_FREE,
            isPremium: $isPremium,
            price: 123,
        );
        $domainService = $this->mock(DomainDriverInterface::class);
        $domainService->expects('check')->with($domain)->andReturns($checkResult);

        $domainDriverFactory = $this->mock(DomainServiceFactory::class);
        $domainDriverFactory->expects('defaultDriver')->andReturns($domainService);

        return $domainDriverFactory;
    }
}
