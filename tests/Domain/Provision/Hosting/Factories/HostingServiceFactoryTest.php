<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Hosting\Factories;

use Illuminate\Validation\Validator as ValidatorContract;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Hosting\Exceptions\UnknownHostingProviderException;
use Waterfront\Domain\Provision\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Provision\Hosting\Services\DirectAdminProvisionService;
use Waterfront\Domain\Provision\Hosting\Services\PleskProvisionService;
use Waterfront\Domain\Provision\Hosting\Validators\DirectAdminValidator;
use Waterfront\Domain\Provision\Hosting\Validators\PleskValidator;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;

#[CoversClass(HostingServiceFactory::class)]
class HostingServiceFactoryTest extends TestCase
{
    #[Test]
    public function validatorDirectAdmin(): void
    {
        $mockDirectAdminValidator = $this->createMock(DirectAdminValidator::class);
        $mockRequest = $this->createStub(ProvisionRequestInterface::class);

        $serviceFactory = new HostingServiceFactory(
            pleskService: $this->createStub(PleskProvisionService::class),
            directadminService: $this->createStub(DirectAdminProvisionService::class),
            directAdminValidator: $mockDirectAdminValidator,
            pleskValidator: $this->createStub(PleskValidator::class)
        );

        $mockDirectAdminValidator->expects(self::once())
            ->method('getValidatorByRequest')
            ->with($mockRequest)
            ->willReturn($this->createStub(ValidatorContract::class));

        $serviceFactory->getValidator(ProvisionProvider::DIRECTADMIN, $mockRequest);
    }

    #[Test]
    public function validatorPlesk(): void
    {
        $mockPleskValidator = $this->createMock(PleskValidator::class);
        $mockRequest = $this->createStub(ProvisionRequestInterface::class);

        $serviceFactory = new HostingServiceFactory(
            pleskService: $this->createStub(PleskProvisionService::class),
            directadminService: $this->createStub(DirectAdminProvisionService::class),
            directAdminValidator: $this->createStub(DirectAdminValidator::class),
            pleskValidator: $mockPleskValidator
        );

        $mockPleskValidator->expects(self::once())
            ->method('getValidatorByRequest')
            ->with($mockRequest)
            ->willReturn($this->createStub(ValidatorContract::class));

        $serviceFactory->getValidator(ProvisionProvider::PLESK, $mockRequest);
    }

    #[Test]
    public function unknownHostingTypeValidatorThrowsException(): void
    {
        $serviceFactory = new HostingServiceFactory(
            pleskService: $this->createStub(PleskProvisionService::class),
            directadminService: $this->createStub(DirectAdminProvisionService::class),
            directAdminValidator: $this->createStub(DirectAdminValidator::class),
            pleskValidator: $this->createStub(PleskValidator::class)
        );

        self::expectException(UnknownHostingProviderException::class);
        self::expectExceptionMessageIs("Can't resolve hosting service from unknown provider [realtimeregister]");

        $serviceFactory->getValidator(ProvisionProvider::RTR, $this->createStub(ProvisionRequestInterface::class));
    }

    #[Test]
    public function providerServices(): void
    {
        $mockPleskService = $this->createStub(PleskProvisionService::class);
        $mockDirectAdminService = $this->createStub(DirectAdminProvisionService::class);

        $serviceFactory = new HostingServiceFactory(
            pleskService: $mockPleskService,
            directadminService: $mockDirectAdminService,
            directAdminValidator: $this->createStub(DirectAdminValidator::class),
            pleskValidator: $this->createStub(PleskValidator::class)
        );

        self::assertSame($mockPleskService, $serviceFactory->getProviderService(ProvisionProvider::PLESK));
        self::assertSame($mockDirectAdminService, $serviceFactory->getProviderService(ProvisionProvider::DIRECTADMIN));
    }

    #[Test]
    public function providerServiceForNonHostingThrowsException(): void
    {
        $serviceFactory = new HostingServiceFactory(
            pleskService: $this->createStub(PleskProvisionService::class),
            directadminService: $this->createStub(DirectAdminProvisionService::class),
            directAdminValidator: $this->createStub(DirectAdminValidator::class),
            pleskValidator: $this->createStub(PleskValidator::class)
        );

        self::expectException(UnknownHostingProviderException::class);
        self::expectExceptionMessageIs("Can't resolve hosting service from unknown provider [realtimeregister]");

        $serviceFactory->getProviderService(ProvisionProvider::RTR);
    }
}
