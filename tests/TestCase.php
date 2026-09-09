<?php

declare(strict_types=1);

namespace Tests;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\Constraint\Constraint;
use Tests\Stubs\InvokableObject;
use Waterfront\Domain\Harbor\Interfaces\CommunicatesWithHarbor;
use Waterfront\Domain\Lighthouse\Services\LighthouseApiService;
use Waterfront\Infra\Configuration\ConfigurationInterface;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // We want to pin the current time so we don't get any flaky tests that fail due to a one-second difference
        CarbonImmutable::setTestNow(CarbonImmutable::now());

        $this->fakeHarbor();
        $this->fakeLighthouse();
    }

    final protected function getConfiguration(): ConfigurationInterface
    {
        return $this->app->get(ConfigurationInterface::class);
    }

    final protected function assertClosureIsCalled(bool $shouldBeCalled, mixed $with = null): Closure
    {
        $mock = $this->createMock(InvokableObject::class);
        $invocation = $mock->expects($shouldBeCalled ? self::atLeastOnce() : self::never())
            ->method('__invoke');

        if ($with !== null) {
            $invocation->with($with);
        }

        return $mock->__invoke(...);
    }

    /**
     * @return Callback<Model>
     */
    final protected function assertCallbackIsModel(Model $expectedModel): Callback
    {
        return self::callback(function (Model $actualModal) use ($expectedModel) {
            self::assertTrue($actualModal->is($expectedModel));
            return true;
        });
    }

    /**
     * @param array<mixed> $firstCallArguments
     * @param array<mixed> ...$consecutiveCallsArguments
     *
     * @return iterable<Callback<mixed>>
     *
     * "Inspired" by: https://github.com/sebastianbergmann/phpunit/issues/4026#issuecomment-1418205424
     */
    final protected static function withConsecutive(array $firstCallArguments, array ...$consecutiveCallsArguments): iterable
    {
        foreach ($consecutiveCallsArguments as $consecutiveCallArguments) {
            self::assertSameSize($firstCallArguments, $consecutiveCallArguments, 'Each expected arguments list need to have the same size.');
        }

        $allConsecutiveCallsArguments = [$firstCallArguments, ...$consecutiveCallsArguments];

        $numberOfArguments = count($firstCallArguments);
        $argumentList      = [];
        for ($argumentPosition = 0; $argumentPosition < $numberOfArguments; $argumentPosition++) {
            $argumentList[$argumentPosition] = array_column($allConsecutiveCallsArguments, $argumentPosition);
        }

        $mockedMethodCall = 0;
        $callbackCall     = 0;
        foreach ($argumentList as $index => $argument) {
            yield self::callback(
                static function (mixed $actualArgument) use ($argumentList, &$mockedMethodCall, &$callbackCall, $index, $numberOfArguments): bool {
                    $expected = $argumentList[$index][$mockedMethodCall] ?? null;

                    $callbackCall++;
                    $mockedMethodCall = (int) ($callbackCall / $numberOfArguments);

                    if ($expected instanceof Constraint) {
                        self::assertThat($actualArgument, $expected);
                    } else {
                        self::assertEquals($expected, $actualArgument);
                    }

                    return true;
                },
            );
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T>|string $abstract
     *
     * @return ($abstract is class-string<T> ? MockInterface&T : MockInterface)
     */
    final protected function mock($abstract, ?Closure $mock = null): mixed
    {
        return parent::mock($abstract, $mock);
    }

    private function fakeHarbor(): void
    {
        $harbor = self::createStub(CommunicatesWithHarbor::class);
        $harbor->method('amqpConnection')->willReturn($this->getMockedAMQPConnection());

        $this->app->bind(CommunicatesWithHarbor::class, fn (): CommunicatesWithHarbor => $harbor);
    }

    private function fakeLighthouse(): void
    {
        $lighthouse = self::createStub(LighthouseApiService::class);
        $this->app->bind(LighthouseApiService::class, fn (): LighthouseApiService => $lighthouse);
    }

    private function getMockedAMQPConnection(): AMQPStreamConnection
    {
        $channel = self::createStub(AMQPChannel::class);
        $channel->method('basic_publish')->willReturn(null);
        $channel->method('queue_bind')->willReturn(null);
        $channel->method('queue_declare')->willReturn(null);
        $channel->method('close')->willReturn(null);

        $connection = self::createStub(AMQPStreamConnection::class);
        $connection->method('channel')->willReturn($channel);
        $connection->method('close')->willReturn(null);

        return $connection;
    }
}
