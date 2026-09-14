<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Microsoft365\Factories;

use Illuminate\Validation\Validator as ValidatorContract;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\UnknownMicrosoft365ProviderException;
use Waterfront\Domain\Provision\Microsoft365\Factories\Microsoft365ServiceFactory;
use Waterfront\Domain\Provision\Microsoft365\Services\MicrosoftOnlineService;
use Waterfront\Domain\Provision\Microsoft365\Validators\MicrosoftOnlineValidator;

#[CoversClass(Microsoft365ServiceFactory::class)]
class Microsoft365ServiceFactoryTest extends TestCase
{
    #[Test]
    public function validatorMicrosoftOnline(): void
    {
        $mockValidator = $this->createMock(MicrosoftOnlineValidator::class);
        $mockRequest = $this->createStub(ProvisionRequestInterface::class);

        $serviceFactory = new Microsoft365ServiceFactory(
            microsoftOnlineValidator: $mockValidator,
            microsoftOnlineService: $this->createStub(MicrosoftOnlineService::class),
        );

        $mockValidator
            ->expects(self::once())
            ->method('getValidatorByRequest')
            ->with($mockRequest)
            ->willReturn($this->createStub(ValidatorContract::class));

        $serviceFactory->getValidator(ProvisionProvider::MICROSOFT_ONLINE, $mockRequest);
    }

    #[Test]
    public function unknownMicrosoft365TypeValidatorThrowsException(): void
    {
        $serviceFactory = new Microsoft365ServiceFactory(
            microsoftOnlineValidator: $this->createStub(MicrosoftOnlineValidator::class),
            microsoftOnlineService: $this->createStub(MicrosoftOnlineService::class),
        );

        self::expectException(UnknownMicrosoft365ProviderException::class);
        self::expectExceptionMessageIs("Can't resolve Microsoft365 service from unknown provider [realtimeregister]");

        $serviceFactory->getValidator(ProvisionProvider::RTR, $this->createStub(ProvisionRequestInterface::class));
    }

    #[Test]
    public function providerServices(): void
    {
        $mockMicrosoftOnlineService = $this->createStub(MicrosoftOnlineService::class);

        $serviceFactory = new Microsoft365ServiceFactory(
            microsoftOnlineValidator: $this->createStub(MicrosoftOnlineValidator::class),
            microsoftOnlineService: $mockMicrosoftOnlineService,
        );

        self::assertSame(
            $mockMicrosoftOnlineService,
            $serviceFactory->getProviderService(ProvisionProvider::MICROSOFT_ONLINE),
        );
    }

    #[Test]
    public function providerServiceForNonMicrosoft365ThrowsException(): void
    {
        $serviceFactory = new Microsoft365ServiceFactory(
            microsoftOnlineValidator: $this->createStub(MicrosoftOnlineValidator::class),
            microsoftOnlineService: $this->createStub(MicrosoftOnlineService::class),
        );

        self::expectException(UnknownMicrosoft365ProviderException::class);
        self::expectExceptionMessageIs("Can't resolve Microsoft365 service from unknown provider [realtimeregister]");

        $serviceFactory->getProviderService(ProvisionProvider::RTR);
    }
}
