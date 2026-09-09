<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\DomainNames\Coupling\Validators;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Str;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\RedirectDeploymentFactory;
use Tests\TestCase;
use Waterfront\Domain\Domains\Rules\DomainNameRule;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\UnknownDomainNameCoupleRequestException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Requests\DomainNameCoupleRequest;
use Waterfront\Domain\Provision\DomainNames\Coupling\Rules\DomainNameCoupleAllowedRule;
use Waterfront\Domain\Provision\DomainNames\Coupling\Validators\DomainNameCoupleValidator;
use Waterfront\Domain\Provision\Enums\ProvisionErrorMessage;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Repositories\ProvisioningDeploymentRepository;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Provision\Rules\DeploymentExistsRule;

#[CoversClass(DomainNameCoupleValidator::class)]
class DomainNameCoupleValidatorTest extends TestCase
{
    private UuidInterface $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = Str::uuid();
        ProvisioningRequestFactory::dontExpandRelationshipsByDefault();
        RedirectDeploymentFactory::dontExpandRelationshipsByDefault();
    }

    #[Test]
    public function getValidatorByRequestWillThrowUnknownDomainNameCoupleRequestException(): void
    {
        $domainNameCoupleValidator = new DomainNameCoupleValidator(
            validatorFactory: self::createStub(Factory::class),
            domainNameRule: self::createStub(DomainNameRule::class),
            deploymentExistsRule: self::createStub(DeploymentExistsRule::class),
            domainNameCoupleAllowedRule: self::createStub(DomainNameCoupleAllowedRule::class),
        );
        self::expectException(UnknownDomainNameCoupleRequestException::class);
        $domainNameCoupleValidator->getValidatorByRequest(self::createStub(ProvisionRequestInterface::class));
    }

    #[Test]
    public function getValidatorByRequestForRedirectWithoutDeployment(): void
    {
        $this->app->bind(function (): ProvisioningDeploymentRepository {
            $mock = self::mock(ProvisioningDeploymentRepository::class);
            $mock->expects('findDeploymentByRequestUuid')->andReturnNull();
            return $mock;
        });

        $mockTranslator = self::createMock(Translator::class);
        $mockTranslator->expects(self::once())->method('get')->willReturn(ProvisionErrorMessage::DEPLOYMENT_NOT_FOUND->value);
        $this->app->bind(Translator::class, fn () => $mockTranslator);

        $domainNameCoupleValidator = new DomainNameCoupleValidator(
            validatorFactory: new Factory($mockTranslator, $this->app),
            domainNameRule: $this->app->make(DomainNameRule::class),
            deploymentExistsRule: $this->app->make(DeploymentExistsRule::class),
            domainNameCoupleAllowedRule: self::createStub(DomainNameCoupleAllowedRule::class),
        );
        self::expectException(ValidationException::class);
        self::expectExceptionMessageIs(ProvisionErrorMessage::DEPLOYMENT_NOT_FOUND->value);
        $validator = $domainNameCoupleValidator->getValidatorByRequest(
            new DomainNameCoupleRequest(
                domain: 'yourhosting.nl',
                requestUuid: Uuid::uuid4(),
                context: $this->context,
            )
        );
        $validator->validate();
    }

    #[Test]
    public function getValidatorByRequestWithInvalidDomain(): void
    {
        $request = new ProvisioningRequestFactory()->redirect()->makeOne();

        $mockTranslator = self::mock(Translator::class);
        $mockTranslator->expects('get')->once()->with('validation.domain_name', [], null)->andReturn('Dit veld bevat geen geldige domeinnaam.');
        $mockTranslator->expects('get')->once()->with('validation.attributes')->andReturn('velden');
        $this->app->bind(Translator::class, fn () => $mockTranslator);

        $domainNameCoupleValidator = new DomainNameCoupleValidator(
            validatorFactory: new Factory($mockTranslator, $this->app),
            domainNameRule: $this->app->make(DomainNameRule::class),
            deploymentExistsRule: self::createStub(DeploymentExistsRule::class),
            domainNameCoupleAllowedRule: self::createStub(DomainNameCoupleAllowedRule::class),
        );
        self::expectException(ValidationException::class);
        self::expectExceptionMessageIs('Dit veld bevat geen geldige domeinnaam.');
        $validator = $domainNameCoupleValidator->getValidatorByRequest(
            new DomainNameCoupleRequest(
                domain: 'invalid',
                requestUuid: $request->uuid,
                context: $this->context,
            )
        );
        $validator->validate();
    }

    #[Test]
    public function validationShouldFailIfCoupleNotAllowed(): void
    {
        $redirectRequest = ProvisioningRequestFactory::new()
            ->redirect()
            ->makeOne();

        $this->app->bind(function (): ProvisioningDeploymentRepository {
            $mock = self::mock(ProvisioningDeploymentRepository::class);
            $mock->expects('findDeploymentByRequestUuid')->andReturn(RedirectDeploymentFactory::new()->makeOne());
            return $mock;
        });

        $this->app->bind(function () use ($redirectRequest): ProvisioningRequestRepository {
            $mock = self::mock(ProvisioningRequestRepository::class);
            $mock->expects('findByUuid')->andReturn($redirectRequest);
            return $mock;
        });

        $mockTranslator = self::createMock(Translator::class);
        $mockTranslator->expects(self::once())->method('get')->willReturn(ProvisionErrorMessage::DOMAIN_NAME_COUPLE_NOT_ALLOWED->value);
        $this->app->bind(Translator::class, fn () => $mockTranslator);

        $redirectRequest->setRelation('deployment', RedirectDeploymentFactory::new()->makeOne());

        $domainNameCoupleValidator = new DomainNameCoupleValidator(
            validatorFactory: new Factory($mockTranslator, $this->app),
            domainNameRule: $this->app->make(DomainNameRule::class),
            deploymentExistsRule: $this->app->make(DeploymentExistsRule::class),
            domainNameCoupleAllowedRule: $this->app->make(DomainNameCoupleAllowedRule::class),
        );

        self::expectException(ValidationException::class);
        self::expectExceptionMessageIs(ProvisionErrorMessage::DOMAIN_NAME_COUPLE_NOT_ALLOWED->value);

        $domainNameCoupleValidator->getValidatorByRequest(
            new DomainNameCoupleRequest(
                domain: 'couple.nl',
                requestUuid: $redirectRequest->uuid,
                context: $this->context,
            )
        )->validate();
    }
}
