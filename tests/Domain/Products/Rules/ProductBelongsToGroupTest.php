<?php

declare(strict_types=1);

namespace Tests\Domain\Products\Rules;

use Closure;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Translation\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Products\Rules\ProductBelongsToGroup;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(ProductBelongsToGroup::class)]
class ProductBelongsToGroupTest extends TestCase
{
    private ProductRepository&MockObject $mockProductRepository;

    private TranslatorInterface&MockObject $mockTranslator;

    /** @var string[] */
    private array $failures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockProductRepository = self::createMock(ProductRepository::class);
        $this->mockTranslator = self::createMock(TranslatorInterface::class);
        $this->failures = [];
    }

    public function testValidateWillPassWhenProductIsInGroup(): void
    {
        $uuid = Uuid::uuid4()->toString();

        $this->mockProductRepository->expects(self::once())
            ->method('productExistsForGroup')
            ->with($uuid, ProductGroupType::ONE_TIME_SERVICE)
            ->willReturn(true);

        $this->mockTranslator->expects(self::never())->method('translate');

        $rule = new ProductBelongsToGroup(
            $this->mockTranslator,
            $this->mockProductRepository,
            ProductGroupType::ONE_TIME_SERVICE,
        );

        $rule->validate('product_uuid', $uuid, $this->failClosure());

        self::assertSame([], $this->failures);
    }

    public function testValidateWillFailWhenProductIsNotInGroup(): void
    {
        $uuid = Uuid::uuid4()->toString();

        $this->mockProductRepository->expects(self::once())
            ->method('productExistsForGroup')
            ->with($uuid, ProductGroupType::ONE_TIME_SERVICE)
            ->willReturn(false);

        $this->mockTranslator->expects(self::once())
            ->method('translate')
            ->with('validation.product_not_in_group')
            ->willReturn('The selected product does not belong to the given product group.');

        $rule = new ProductBelongsToGroup(
            $this->mockTranslator,
            $this->mockProductRepository,
            ProductGroupType::ONE_TIME_SERVICE,
        );

        $rule->validate('product_uuid', $uuid, $this->failClosure());

        self::assertSame(['The selected product does not belong to the given product group.'], $this->failures);
    }

    public function testValidateWillFailWhenValueIsNotAString(): void
    {
        $this->mockProductRepository->expects(self::never())->method('productExistsForGroup');

        $this->mockTranslator->expects(self::once())
            ->method('translate')
            ->with('validation.product_not_in_group')
            ->willReturn('The selected product does not belong to the given product group.');

        $rule = new ProductBelongsToGroup(
            $this->mockTranslator,
            $this->mockProductRepository,
            ProductGroupType::ONE_TIME_SERVICE,
        );

        $rule->validate('product_uuid', 123, $this->failClosure());

        self::assertSame(['The selected product does not belong to the given product group.'], $this->failures);
    }

    public function testValidateWillCheckAgainstTheConfiguredGroup(): void
    {
        $uuid = Uuid::uuid4()->toString();

        $this->mockProductRepository->expects(self::once())
            ->method('productExistsForGroup')
            ->with($uuid, ProductGroupType::HOSTING)
            ->willReturn(true);

        $this->mockTranslator->expects(self::never())->method('translate');

        $rule = new ProductBelongsToGroup(
            $this->mockTranslator,
            $this->mockProductRepository,
            ProductGroupType::HOSTING,
        );

        $rule->validate('product_uuid', $uuid, $this->failClosure());

        self::assertSame([], $this->failures);
    }

    private function failClosure(): Closure
    {
        return function (string $message): PotentiallyTranslatedString {
            $this->failures[] = $message;

            return new PotentiallyTranslatedString($message, $this->app->make(Translator::class));
        };
    }
}
