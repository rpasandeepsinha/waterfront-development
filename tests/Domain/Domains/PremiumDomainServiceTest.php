<?php

declare(strict_types=1);

namespace Tests\Domain\Domains;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Domain\Domains\Mailers\PremiumDomainPriceRequested;
use Waterfront\Domain\Domains\Services\PremiumDomainService;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Mailer\MailTemplateInterface;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\ProductPeriod;

#[CoversClass(PremiumDomainService::class)]
#[AllowMockObjectsWithoutExpectations]
class PremiumDomainServiceTest extends IntegrationTestCase
{
    private PremiumDomainService $service;

    private MailerInterface&MockObject $mailer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailer = self::createMock(MailerInterface::class);
        $this->app->bind(MailerInterface::class, fn () => $this->mailer);

        $this->service = new PremiumDomainService($this->mailer, $this->getConfiguration());
    }

    #[Test]
    public function requestPremiumPricePerEmail(): void
    {
        Config::set('bu.support_email', 'support@test.com');
        $domain = 'testdomain.com';
        $customerEmailAddress = 'test@test.com';

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(
                self::callback(
                    fn (array $recipients): bool => count($recipients) === 1
                        && $recipients[0]->getEmail() === 'support@test.com'
                ),
                self::callback(
                    fn (MailTemplateInterface $template): bool => $template::class === PremiumDomainPriceRequested::class
                )
            );

        $this->service->requestPremiumPricePerEmail($domain, $customerEmailAddress);
    }

    #[Test]
    public function getPremiumDomainProductSlug(): void
    {
        self::assertSame('extension_premium_fast_cars', $this->service->getPremiumDomainProductSlug('fast.cars'));
        self::assertSame('extension_premium_rich_co.uk', $this->service->getPremiumDomainProductSlug('rich.co.uk'));
        self::assertSame('extension_premium_localhost', $this->service->getPremiumDomainProductSlug('localhost'));
    }

    #[Test]
    public function doesPoductExistForPremiumDomain(): void
    {
        $this->setupTestData();

        self::assertFalse($this->service->doesProductExistForPremiumDomain('quick.bikes'));
        self::assertTrue($this->service->doesProductExistForPremiumDomain('fast.cars'));
    }

    #[Test]
    public function createProductPriceForPremiumDomainInvalidMargin(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->createProductPriceForPremiumDomain(
            new CheckResult('quick.bikes', CheckResult::STATUS_FREE, ''),
            -1
        );
    }

    #[Test]
    public function createProductPriceForPremiumDomainNotPremium(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->createProductPriceForPremiumDomain(
            new CheckResult('quick.bikes', CheckResult::STATUS_FREE, '', false, 1000),
            25
        );
    }

    #[Test]
    public function createProductPriceForPremiumDomainPremiumNotSupported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->createProductPriceForPremiumDomain(
            new CheckResult('quick.bikes', CheckResult::STATUS_FREE, ''),
            25
        );
    }

    #[Test]
    public function createProductPriceForPremiumDomainProductAlreadyExists(): void
    {
        $this->setupTestData();
        $this->expectException(InvalidArgumentException::class);
        $this->service->createProductPriceForPremiumDomain(
            new CheckResult('fast.cars', CheckResult::STATUS_FREE, '', true, 1000),
            25
        );
    }

    #[Test]
    public function createProductPriceForPremiumDomainProduct(): void
    {
        $this->setupTestData();
        $product = $this->service->createProductPriceForPremiumDomain(
            new CheckResult('quick.bikes', CheckResult::STATUS_FREE, '', true, 14411),
            25
        );

        self::assertTrue($product->exists());
        self::assertSame('extension_premium_quick_bikes', $product->slug);

        $registrationPrice = ProductPriceComponent::where('product_id', $product->id)->where('type', PriceComponentType::REGISTRATION)->firstOrFail();

        self::assertSame(18014, $registrationPrice->price);
        self::assertSame(12, $registrationPrice->contract_period);
        self::assertSame(12, $registrationPrice->billing_period);

        $productPeriods = ProductPeriod::where('product_id', $product->id)->get();
        self::assertCount(1, $productPeriods);
        self::assertSame(12, $productPeriods->firstOrFail()->billing_period);
        self::assertSame(12, $productPeriods->firstOrFail()->contract_period);
    }

    private function setupTestData(): void
    {
        $group = new ProductGroupFactory()->createOne(['slug' => ProductGroupType::EXTENSION]);
        new ProductFactory()->for($group)->createOne(['name' => '.nl', 'slug' => 'extension_premium_fast_cars']);
    }
}
