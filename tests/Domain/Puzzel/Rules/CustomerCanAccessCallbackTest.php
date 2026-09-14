<?php

declare(strict_types=1);

namespace Tests\Domain\Puzzel\Rules;

use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Translation\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Puzzel\Rules\CustomerCanAccessCallback;
use Waterfront\Domain\Puzzel\Rules\NoFutureScheduledCalls;
use Waterfront\Domain\Subscriptions\Services\ServicePlanChecker;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;
use Waterfront\Infra\Translation\Translator as WaterfrontTranslator;

#[CoversClass(NoFutureScheduledCalls::class)]
class CustomerCanAccessCallbackTest extends IntegrationTestCase
{
    #[Test]
    public function validation(): void
    {
        $customer = CustomerFactory::new()->createOne();
        $fail = false;

        $mockAuth = self::createMock(AuthenticationManager::class);
        $mockAuth
            ->expects($this->once())
            ->method('getAuthenticatedSubject')
            ->willReturn(new AuthenticatedCustomer(
                customer: $customer,
                identitySchema: self::createStub(KratosIdentity::class),
                verified: true,
            ));

        $mockTranslator = self::createMock(WaterfrontTranslator::class);
        $mockTranslator
            ->expects($this->once())
            ->method('translate')
            ->with('validation.puzzel-no-callback-access')
            ->willReturn('validation.puzzel-no-callback-access');

        $mockCallbackRepo = self::createMock(ServicePlanChecker::class);
        $mockCallbackRepo->expects($this->once())->method('hasAccessToServicePlan')->with($customer)->willReturn(false);

        $rule = new CustomerCanAccessCallback($mockTranslator, $mockAuth, $mockCallbackRepo);

        $rule->validate('attribute', 123, function (string $message, ?string $attribute = null) use (&$fail) {
            self::assertSame('validation.puzzel-no-callback-access', $message);
            $fail = true;

            return new PotentiallyTranslatedString('fail', $this->app->make(Translator::class));
        });

        self::assertTrue($fail);
    }
}
