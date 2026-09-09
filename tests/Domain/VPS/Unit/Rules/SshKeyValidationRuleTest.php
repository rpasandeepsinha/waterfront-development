<?php

declare(strict_types=1);

namespace Tests\Domain\VPS\Unit\Rules;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\VPS\Models\SshKey;
use Waterfront\Domain\VPS\Repositories\SshKeyRepository;
use Waterfront\Domain\VPS\Rules\SshKeyValidationRule;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(SshKeyValidationRule::class)]
class SshKeyValidationRuleTest extends TestCase
{
    #[Test]
    public function withoutSshKey(): void
    {
        $rule = new SshKeyValidationRule(
            customer: self::createStub(Customer::class),
            translator: self::createStub(TranslatorInterface::class),
            productRepository: self::createStub(ProductRepository::class),
            productSpecRepository: $productSpecRepository = self::createMock(ProductSpecRepository::class),
            sshKeyRepository: self::createStub(SshKeyRepository::class)
        );

        $productSpecRepository->expects(self::once())
            ->method('booleanSpecificationIsTrue')
            ->willReturn(false);

        $rule->validate('attribute', ['slug' => 'dummy-slug'], self::assertClosureIsCalled(false));
    }

    #[Test]
    public function withoutSshKeyFailsWhenSshKeyIsProvided(): void
    {
        $rule = new SshKeyValidationRule(
            customer: self::createStub(Customer::class),
            translator: $translator = self::createMock(TranslatorInterface::class),
            productRepository: self::createStub(ProductRepository::class),
            productSpecRepository: $productSpecRepository = self::createMock(ProductSpecRepository::class),
            sshKeyRepository: self::createStub(SshKeyRepository::class)
        );

        $message = 'validation.vps.ssh-key-not-required';
        $translator->expects(self::once())
            ->method('translate')
            ->with($message)
            ->willReturn($message);

        $productSpecRepository->expects(self::once())
            ->method('booleanSpecificationIsTrue')
            ->willReturn(false);

        $rule->validate('attribute', ['slug' => 'dummy-slug', 'ssh_key_uuid' => 'some-ssh-key-uuid'], self::assertClosureIsCalled(true, $message));
    }

    #[Test]
    public function withSshKey(): void
    {
        $rule = new SshKeyValidationRule(
            customer: self::createStub(Customer::class),
            translator: self::createStub(TranslatorInterface::class),
            productRepository: self::createStub(ProductRepository::class),
            productSpecRepository: $productSpecRepository = self::createMock(ProductSpecRepository::class),
            sshKeyRepository: $sshKeyRepository = self::createMock(SshKeyRepository::class)
        );

        $productSpecRepository->expects(self::once())
            ->method('booleanSpecificationIsTrue')
            ->willReturn(true);

        $sshKeyRepository->expects(self::once())
            ->method('findByCustomerAndUuid')
            ->willReturn(self::createStub(SshKey::class));

        $rule->validate('attribute', ['slug' => 'dummy-slug', 'ssh_key_uuid' => 'some-ssh-key-uuid'], self::assertClosureIsCalled(false));
    }

    #[Test]
    public function withSshKeyFailsWhenSshKeyNotProvided(): void
    {
        $rule = new SshKeyValidationRule(
            customer: self::createStub(Customer::class),
            translator: $translator = self::createMock(TranslatorInterface::class),
            productRepository: self::createStub(ProductRepository::class),
            productSpecRepository: $productSpecRepository = self::createMock(ProductSpecRepository::class),
            sshKeyRepository: self::createStub(SshKeyRepository::class)
        );

        $message = 'validation.vps.ssh-key-required';
        $translator->expects(self::once())
            ->method('translate')
            ->with($message)
            ->willReturn($message);

        $productSpecRepository->expects(self::once())
            ->method('booleanSpecificationIsTrue')
            ->willReturn(true);

        $rule->validate('attribute', ['slug' => 'dummy-slug'], self::assertClosureIsCalled(true, $message));
    }

    #[Test]
    public function withSshKeyFailsWhenSshKeyNotFound(): void
    {
        $rule = new SshKeyValidationRule(
            customer: self::createStub(Customer::class),
            translator: $translator = self::createMock(TranslatorInterface::class),
            productRepository: self::createStub(ProductRepository::class),
            productSpecRepository: $productSpecRepository = self::createMock(ProductSpecRepository::class),
            sshKeyRepository: $sshKeyRepository = self::createMock(SshKeyRepository::class)
        );

        $message = 'validation.vps.ssh-key-not-found';
        $translator->expects(self::once())
            ->method('translate')
            ->with($message)
            ->willReturn($message);

        $productSpecRepository->expects(self::once())
            ->method('booleanSpecificationIsTrue')
            ->willReturn(true);

        $sshKeyRepository->expects(self::once())
            ->method('findByCustomerAndUuid')
            ->willThrowException(new ModelNotFoundException());

        $rule->validate('attribute', ['slug' => 'dummy-slug', 'ssh_key_uuid' => 'some-ssh-key-uuid'], self::assertClosureIsCalled(true, $message));
    }
}
