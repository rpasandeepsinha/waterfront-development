<?php

declare(strict_types=1);

namespace Tests\Infra\Configuration;

use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\Configuration\Configuration;
use Waterfront\Infra\Configuration\ConfigurationException;

#[CoversClass(Configuration::class)]
#[AllowMockObjectsWithoutExpectations]
class ConfigurationTest extends TestCase
{
    #[Test]
    public function getAsStringShouldReturnString(): void
    {
        $laravelConfig = $this->createStub(Repository::class);
        $laravelConfig->method('get')->willReturn('testValue');

        $configuration = new Configuration($laravelConfig);
        $value = $configuration->getAsString('testKey');

        self::assertSame('testValue', $value);
    }

    #[Test]
    public function getAsStringShouldThrowExceptionOnMissingValue(): void
    {
        $this->expectException(ConfigurationException::class);

        $laravelConfig = $this->createStub(Repository::class);
        $laravelConfig->method('get')->willReturn(null);

        $configuration = new Configuration($laravelConfig);
        $configuration->getAsString('testKey');
    }

    #[DataProvider('invalidStringValues')]
    #[Test]
    public function getAsStringShouldThrowExceptionOnInvalidValue(mixed $value): void
    {
        $this->expectException(ConfigurationException::class);

        $laravelConfig = $this->createStub(Repository::class);
        $laravelConfig->method('get')->willReturn($value);

        $configuration = new Configuration($laravelConfig);
        $configuration->getAsString('testKey');
    }

    /** @return iterable<string, mixed> */
    public static function invalidStringValues(): iterable
    {
        yield 'array' => [['testarray' => 'testvalue']];
        yield 'integer' => [11];
    }

    #[DataProvider('validBooleanValues')]
    #[Test]
    public function getAsBooleanShouldReturnBoolean(mixed $input, bool $expected): void
    {
        $laravelConfig = $this->createStub(Repository::class);
        $laravelConfig->method('get')->willReturn($input);

        $configuration = new Configuration($laravelConfig);
        $value = $configuration->getAsBoolean('testKey');

        self::assertSame($expected, $value);
    }

    /** @return iterable<string, mixed> */
    public static function validBooleanValues(): iterable
    {
        yield 'string true' => ['true', true];
        yield 'string false' => ['false', true];
        yield 'string 1' => ['1', true];
        yield 'integer 1' => [1, true];
        yield 'boolean true' => [true, true];

        yield 'string 0' => ['0', false];
        yield 'integer 0' => [0, false];
        yield 'boolean false' => [false, false];
    }

    #[Test]
    public function getAsBooleanShouldThrowExceptionOnMissingValue(): void
    {
        $this->expectException(ConfigurationException::class);

        $laravelConfig = $this->createStub(Repository::class);
        $laravelConfig->method('get')->willReturn(null);

        $configuration = new Configuration($laravelConfig);
        $configuration->getAsBoolean('testKey');
    }

    #[DataProvider('invalidStringValues')]
    #[Test]
    public function getAsBooleanShouldThrowExceptionOnInvalidValue(mixed $value): void
    {
        $this->expectException(ConfigurationException::class);

        $laravelConfig = $this->createStub(Repository::class);
        $laravelConfig->method('get')->willReturn($value);

        $configuration = new Configuration($laravelConfig);
        $configuration->getAsBoolean('testKey');
    }

    /** @return iterable<string, mixed> */
    public function invalidBooleanValues(): iterable
    {
        yield 'array' => [['testarray' => 'testvalue']];
        yield 'integer' => [11];
    }

    #[DataProvider('validIntegerValues')]
    #[Test]
    public function getAsIntegerShouldReturnInteger(mixed $input, int $expected): void
    {
        $laravelConfig = $this->createStub(Repository::class);
        $laravelConfig->method('get')->willReturn($input);

        $configuration = new Configuration($laravelConfig);
        $value = $configuration->getAsInteger('testKey');

        self::assertSame($expected, $value);
    }

    /** @return iterable<string, mixed> */
    public static function validIntegerValues(): iterable
    {
        yield 'string 1' => ['1', 1];
        yield 'string 000' => ['000', 0];
        yield 'integer 1' => [1, 1];
        yield 'float 0.1' => [0.1, 0];
        yield 'float 1.0' => [1.0, 1];
    }

    #[Test]
    public function getAsIntegerShouldThrowExceptionOnMissingValue(): void
    {
        $this->expectException(ConfigurationException::class);

        $laravelConfig = $this->createStub(Repository::class);
        $laravelConfig->method('get')->willReturn(null);

        $configuration = new Configuration($laravelConfig);
        $configuration->getAsInteger('testKey');
    }

    #[DataProvider('invalidIntegerValues')]
    #[Test]
    public function getAsIntegerShouldThrowExceptionOnInvalidValue(mixed $value): void
    {
        $this->expectException(ConfigurationException::class);

        $laravelConfig = $this->createStub(Repository::class);
        $laravelConfig->method('get')->willReturn($value);

        $configuration = new Configuration($laravelConfig);
        $configuration->getAsInteger('testKey');
    }

    /** @return iterable<string, mixed> */
    public static function invalidIntegerValues(): iterable
    {
        yield 'array' => [['testarray' => 'testvalue']];
        yield 'string' => ['test'];
    }

    #[DataProvider('validFloatValues')]
    #[Test]
    public function getAsFloatShouldReturnFloat(mixed $input, float $expected): void
    {
        $laravelConfig = $this->createStub(Repository::class);
        $laravelConfig->method('get')->willReturn($input);

        $configuration = new Configuration($laravelConfig);
        $value = $configuration->getAsFloat('testKey');

        self::assertSame($expected, $value);
    }

    /** @return iterable<string, mixed> */
    public static function validFloatValues(): iterable
    {
        yield 'string 1' => ['1', 1.000];
        yield 'string 0' => ['0', 0];
        yield 'string 000' => ['000', 0];
        yield 'integer 1' => [1, 1.00];
        yield 'float 0.1' => [0.1, 0.1];
        yield 'float 1.0' => [1.0, 1.0];
        yield 'float 1.0001' => [1.0001, 1.0001];
    }

    #[Test]
    public function getAsFloatShouldThrowExceptionOnMissingValue(): void
    {
        $this->expectException(ConfigurationException::class);

        $laravelConfig = $this->createStub(Repository::class);
        $laravelConfig->method('get')->willReturn(null);

        $configuration = new Configuration($laravelConfig);
        $configuration->getAsFloat('testKey');
    }

    #[DataProvider('invalidFloatValues')]
    #[Test]
    public function getAsFloatShouldThrowExceptionOnInvalidValue(mixed $value): void
    {
        $this->expectException(ConfigurationException::class);

        $laravelConfig = $this->createStub(Repository::class);
        $laravelConfig->method('get')->willReturn($value);

        $configuration = new Configuration($laravelConfig);
        $configuration->getAsFloat('testKey');
    }

    /** @return iterable<string, mixed> */
    public static function invalidFloatValues(): iterable
    {
        yield 'array' => [['testarray' => 'testvalue']];
        yield 'string' => ['test'];
    }
}
