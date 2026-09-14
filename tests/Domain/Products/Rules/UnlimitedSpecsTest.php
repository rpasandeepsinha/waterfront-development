<?php

declare(strict_types=1);

namespace Tests\Domain\Products\Rules;

use Closure;
use Illuminate\Support\Facades\Config;
use Laravel\Nova\Http\Requests\NovaRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Rules\ProductSpecValue;

#[CoversClass(ProductSpecValue::class)]
class UnlimitedSpecsTest extends IntegrationTestCase
{
    #[Test]
    public function minusOneAsString(): void
    {
        $this->expectNotToPerformAssertions();

        $validValue = '-1';
        $request = self::resolve(NovaRequest::class);
        $request->name = 'hosting.limits.disk_space';
        $productSpecValue = new ProductSpecValue($request);

        $productSpecValue->validate('value', $validValue, $this->getFailure());
    }

    #[Test]
    public function unlimitedValueMinusOneFailedOnNoneNumeric(): void
    {
        $invalidValue = 'dsgaydsffasdufhasdfhaydsufhadsfasdfhhhh12h1h1hh1h1';

        $request = self::resolve(NovaRequest::class);
        $request->name = 'hosting.limits.disk_space';
        $productSpecValue = new ProductSpecValue($request);

        $productSpecValue->validate('value', $invalidValue, $this->getSucces('Unspecified validation error'));
    }

    #[Test]
    public function productSpecValueTypeAsInteger(): void
    {
        $this->expectNotToPerformAssertions();

        $validValue = '-1';
        Config::set('product-specs.hosting.limits.disk_space', [
            'type' => 'integer',
            'show-in-form' => true,
            'default' => null,
        ]);
        $request = self::resolve(NovaRequest::class);
        $request->name = 'hosting.limits.disk_space';
        $productSpecValue = new ProductSpecValue(self::resolve(NovaRequest::class));
        $productSpecValue->validate('value', $validValue, $this->getSucces('Unspecified validation error'));
    }

    #[Test]
    public function productSpecValueTypeAsBytes(): void
    {
        $this->expectNotToPerformAssertions();

        $validValue = '-1';
        Config::set('product-specs.hosting.limits.disk_space', [
            'type' => 'bytes',
            'show-in-form' => true,
            'default' => null,
        ]);
        $request = self::resolve(NovaRequest::class);
        $request->name = 'hosting.limits.disk_space';
        $productSpecValue = new ProductSpecValue(self::resolve(NovaRequest::class));
        $productSpecValue->validate('value', $validValue, $this->getFailure());
    }

    #[Test]
    public function productSpecValueTypeFailedOnIntegerOrBytes(): void
    {
        $validValue = '-1';
        Config::set('product-specs.hosting.limits.disk_space', [
            'type' => 'unknown',
            'show-in-form' => true,
            'default' => null,
        ]);
        $request = self::resolve(NovaRequest::class);
        $request->name = 'hosting.limits.disk_space';
        $productSpecValue = new ProductSpecValue(self::resolve(NovaRequest::class));
        $productSpecValue->validate('value', $validValue, $this->getSucces('Unspecified validation error'));
    }

    private function getFailure(): Closure
    {
        return function (string $message): never {
            self::fail(sprintf('Validator failed with message: %s', $message));
        };
    }

    private function getSucces(string $expectedMessage): Closure
    {
        return function (string $message) use ($expectedMessage): void {
            self::assertSame($expectedMessage, $message);
        };
    }
}
