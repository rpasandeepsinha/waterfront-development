<?php

declare(strict_types=1);

namespace Tests\Domain\Puzzel\Rules;

use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Translation\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use Tests\Factories\PuzzelCallbackRequestFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Puzzel\Repositories\PuzzelCallbackRequestRepository;
use Waterfront\Domain\Puzzel\Rules\NoFutureScheduledCalls;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;
use Waterfront\Infra\Translation\Translator as WaterfrontTranslator;

#[CoversClass(NoFutureScheduledCalls::class)]
class NoFutureScheduledCallsTest extends IntegrationTestCase
{
    #[Test]
    public function validation(): void
    {
        $existingRequest = PuzzelCallbackRequestFactory::new()->createOne();

        $fail = false;

        $mockAuth = self::createMock(AuthenticationManager::class);
        $mockAuth->expects($this->once())
            ->method('getAuthenticatedCustomer')
            ->willReturn(new AuthenticatedCustomer(
                customer: $existingRequest->customer,
                identitySchema: self::createStub(KratosIdentity::class),
                verified: true,
            ));

        $mockTranslator = self::createMock(WaterfrontTranslator::class);
        $mockTranslator->expects($this->once())
            ->method('translate')
            ->with('validation.puzzel-existing-request')
            ->willReturn('validation.puzzel-existing-request');

        $mockCallbackRepo = self::createMock(PuzzelCallbackRequestRepository::class);
        $mockCallbackRepo->expects($this->once())
            ->method('findFirstFutureForCustomer')
            ->willReturn($existingRequest);

        $rule = new NoFutureScheduledCalls($mockTranslator, $mockCallbackRepo, $mockAuth);

        $rule->validate('attribute', 123, function (string $message, ?string $attribute = null) use (&$fail) {
            self::assertSame('validation.puzzel-existing-request', $message);
            $fail = true;
            return new PotentiallyTranslatedString('fail', $this->app->make(Translator::class));
        });

        self::assertTrue($fail);
    }
}
