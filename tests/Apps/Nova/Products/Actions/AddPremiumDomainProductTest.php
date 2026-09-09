<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Products\Actions;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\ActionRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Apps\Nova\Products\Actions\NovaAddPremiumDomainProductAction;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Interfaces\DomainDriverInterface;
use Waterfront\Domain\Domains\Services\PremiumDomainService;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(NovaAddPremiumDomainProductAction::class)]
#[AllowMockObjectsWithoutExpectations]
class AddPremiumDomainProductTest extends TestCase
{
    #[Test]
    public function handle(): void
    {
        $checkResult = new CheckResult(
            domain: 'fast.cars',
            status: CheckResult::STATUS_FREE,
            isPremium: true,
            price: 123,
        );
        $domainService = $this->mock(DomainDriverInterface::class);
        $domainService->expects('check')->with('fast.cars')->andReturns($checkResult);

        $factory = self::createStub(DomainServiceFactory::class);
        $factory->method('defaultDriver')->willReturn($domainService);

        $premiumDomainProducts = $this->mock(PremiumDomainService::class);
        $premiumDomainProducts->expects('createProductPriceForPremiumDomain')
            ->once()
            ->withArgs([$checkResult, 25])
            ->andReturn(new Product());

        $translator = self::createStub(TranslatorInterface::class);

        $action = new NovaAddPremiumDomainProductAction($factory, $premiumDomainProducts, $translator);
        $action->handle(new ActionFields(new Collection(['domain' => 'fast.cars', 'margin' => 25]), new Collection([])), new Collection([]));
    }

    #[DataProvider('getTestValidationData')]
    #[Test]
    public function validation(string $domain, int $margin, CheckResult $checkResult, bool $priceAlreadyExists, bool $isValid): void
    {
        $domainService = $this->mock(DomainDriverInterface::class);
        $domainService->expects('check')->with($domain)->andReturns($checkResult);

        $factory = self::createStub(DomainServiceFactory::class);
        $factory->method('defaultDriver')->willReturn($domainService);

        $premiumDomainProducts = $this->mock(PremiumDomainService::class);
        $premiumDomainProducts->expects('createProductPriceForPremiumDomain')->never();
        $premiumDomainProducts->expects('doesProductExistForPremiumDomain')->zeroOrMoreTimes()->andReturns($priceAlreadyExists);

        $translator = self::createStub(TranslatorInterface::class);

        $action = new NovaAddPremiumDomainProductAction($factory, $premiumDomainProducts, $translator);
        if (! $isValid) {
            $this->expectException(ValidationException::class);
        }
        $action->validateFields(new ActionRequest(['domain' => $domain, 'margin' => $margin]));
    }

    /** @return array<string,array<mixed>> */
    public static function getTestValidationData(): array
    {
        return [
            'Valid domain' => [
                'fast.cars',
                25,
                new CheckResult(
                    domain: 'fast.cars',
                    status: CheckResult::STATUS_FREE,
                    isPremium: true,
                    price: 123,
                ),
                false,
                true,
            ],
            'Domain is not premium' => [
                'fast.cars',
                25,
                new CheckResult(
                    domain: 'fast.cars',
                    status: CheckResult::STATUS_FREE,
                    isPremium: false,
                    price: 123,
                ),
                false,
                false,
            ],
            'Premium domains are not supported' => [
                'fast.cars',
                25,
                new CheckResult(
                    domain: 'fast.cars',
                    status: CheckResult::STATUS_FREE,
                ),
                false,
                false,
            ],
            'Product already exists' => [
                'fast.cars',
                25,
                new CheckResult(
                    domain: 'fast.cars',
                    status: CheckResult::STATUS_FREE,
                    isPremium: true,
                    price: 123,
                ),
                true,
                false,
            ],
        ];
    }
}
