<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\CloudStack;

use Illuminate\Http\Response;
use phpseclib3\Crypt\Common\PublicKey;
use phpseclib3\Crypt\RSA;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CloudstackEnvironmentFactory;
use Tests\Factories\CloudstackManagerDomainDeploymentFactory;
use Tests\Factories\CloudstackVirtualMachineDeploymentFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SshKeyFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\CloudStack\SshKeyController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\VPS\Actions\DeleteSshKeyAction;

#[CoversClass(SshKeyController::class)]
class SshKeyTest extends IntegrationTestCase
{
    private Customer $customer;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function indexEndpointEmptyResult(): void
    {
        $this->actingAsCustomer($this->customer)->getJson(
            $this->generateRoute('partners.cloudstack.ssh-key.index')
        )->assertOk()
            ->assertJsonStructure(['data']);
    }

    #[Test]
    public function indexEndpoint(): void
    {
        new SshKeyFactory()
             ->for($this->customer)
             ->createMany(3);

        $this->actingAsCustomer($this->customer)->getJson(
            $this->generateRoute('partners.cloudstack.ssh-key.index')
        )->assertOk()
             ->assertJsonStructure(['data']);
    }

    #[Test]
    public function createEndpoint(): void
    {
        $sshKey = RSA::createKey()->getPublicKey();
        self::assertInstanceOf(PublicKey::class, $sshKey);

        $keyName = 'test-key_for 1908';
        $sshKeyString = $sshKey->toString('OpenSSH');

        $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.cloudstack.ssh-key.create', [
                'key_name' => $keyName,
                'ssh_key' => $sshKeyString,
            ])
        )->assertStatus(Response::HTTP_CREATED)
        ->assertJsonStructure([
            'uuid',
            'key_name',
            'fingerprint',
        ]);

        $this->assertDatabaseHas('cloudstack_vm_ssh_keys', [
            'key_name' => $keyName,
            'public_key' => $sshKeyString,
            'fingerprint' => $sshKey->getFingerprint('md5'),
        ]);
    }

    #[Test]
    public function createWithCommentEndpoint(): void
    {
        $format = 'ssh-rsa';
        $key = 'AAAAB3NzaC1yc2EAAAADAQABAAABgQCoKCP3q3pJ5ONa/5P7jefj4itg+z2bpDH5SUt80JojTk7IZszGvm1Yh2WeFzPuoEbEZ9BYAdsj3+OqBoaVZoCfNjdFtt6s0nbAmN7gGSiCg6u7Me1alK4Vo8w0Lp2k9+SVabKHNIHlATE/EjEGJ4ULIc9zTDbA2xw2q0GdP3wKREYjeZZIiP1/1jPcQohBWQFhHSY7hFA337uzH97bnPDdFWQ8ZVAarqb/CZ+UvT1WMvAUOWId+rws/wldaHdxsvzUeGj03kSkZ8OMr/07Vhuo3yjyMN9vwWq4Mi2LapmwTcOZjk1rNkA3Ft7wmKl0uC8I1RptH5QkWsVEHZZ4AjP2w9I0SgbsohWTq4i7k1gUqJwT0h7k6Deo1cdkvt3L+XjMe9RUF+DxYNbtdJxTsGTNk6sXS6wwQH5RXHvEiJCPTrlSlmLb0niy0QGip935PdCxJ9lqpEOIkHiE813uByIruuGZaCSVHc6p3lZmcIMTGHAK5CBJee9oVbwrpsRcL58=';
        $comment = null;
        $keyName = 'test-key_for 1908';
        $md5 = '56:e3:6e:71:7e:ef:f2:4f:e0:e6:d9:38:2f:eb:a4:c2';
        $sshKeyString = sprintf('%s %s %s', $format, $key, $comment);

        $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.cloudstack.ssh-key.create', [
                'key_name' => $keyName,
                'ssh_key' => $sshKeyString,
            ])
        )->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure([
                'uuid',
                'key_name',
                'fingerprint',
            ]);

        $this->assertDatabaseHas('cloudstack_vm_ssh_keys', [
            'key_name' => $keyName,
            'public_key' => $sshKeyString,
            'fingerprint' => $md5,
        ]);
    }

    #[Test]
    public function createEndpointWithDuplicateKey(): void
    {
        $alreadyStoredKey = new SshKeyFactory()
            ->for($this->customer)
            ->createOne();

        $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.cloudstack.ssh-key.create', [
                'key_name' => 'new-name',
                'ssh_key' => $alreadyStoredKey->public_key,
            ])
        )->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function createEndpointWithInvalidKey(): void
    {
        $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.cloudstack.ssh-key.create', [
                'key_name' => 'test-key',
                'ssh_key' => 'invalid key',
            ])
        )->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function deleteEndpointSuccess(): void
    {
        $sshKey = new SshKeyFactory()
            ->for($this->customer)
            ->createOne();

        $environment = new CloudstackEnvironmentFactory()->createOne();

        $managerDomain = new CloudstackManagerDomainDeploymentFactory()
            ->for($this->customer)
            ->for($environment)
            ->createOne();

        $vmDeployment = new CloudstackVirtualMachineDeploymentFactory()
            ->for(
                SubscriptionFactory::new()
                    ->for($this->customer)
                    ->for(new ProductFactory()->vps())
            )
            ->for($managerDomain)
            ->createOne();

        $sshKey->managerDomains()->save($managerDomain);
        $sshKey->virtualMachineDeployments()->save($vmDeployment);

        $deleteSshKeyActionMock = self::createMock(DeleteSshKeyAction::class);
        $deleteSshKeyActionMock->expects(self::once())
            ->method('execute')
            ->with(
                self::assertCallbackIsModel($sshKey),
                $this->customer
            )
            ->willReturn(true);

        $this->app->bind(DeleteSshKeyAction::class, fn (): DeleteSshKeyAction =>  $deleteSshKeyActionMock);

        $this->actingAsCustomer($this->customer)->deleteJson(
            $this->generateRoute('partners.cloudstack.ssh-key.destroy', ['sshKey' => $sshKey->uuid])
        )->assertStatus(Response::HTTP_NO_CONTENT);
    }

    #[Test]
    public function deleteEndpointFailedKeyNotDeletable(): void
    {
        $sshKey = new SshKeyFactory()
            ->for($this->customer)
            ->createOne();

        $environment = new CloudstackEnvironmentFactory()->createOne();

        $vpsSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->vps());

        $managerDomain = new CloudstackManagerDomainDeploymentFactory()
            ->for($this->customer)
            ->for($environment)
            ->createOne();

        $vmDeployment = new CloudstackVirtualMachineDeploymentFactory()
            ->for($vpsSubscription)
            ->for($managerDomain)
            ->createOne();

        $sshKey->managerDomains()->save($managerDomain);
        $sshKey->virtualMachineDeployments()->save($vmDeployment);

        $this->actingAsCustomer($this->customer)->deleteJson(
            $this->generateRoute('partners.cloudstack.ssh-key.destroy', ['sshKey' => $sshKey->uuid])
        )->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Test]
    public function deleteEndpointFailed(): void
    {
        $sshKey = new SshKeyFactory()
            ->for($this->customer)
            ->createOne();

        $environment = new CloudstackEnvironmentFactory()->createOne();

        $vpsSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->vps());

        $managerDomain = new CloudstackManagerDomainDeploymentFactory()
            ->for($this->customer)
            ->for($environment)
            ->createOne();

        $vmDeployment = new CloudstackVirtualMachineDeploymentFactory()
            ->for($vpsSubscription)
            ->for($managerDomain)
            ->createOne();

        $sshKey->managerDomains()->save($managerDomain);
        $sshKey->virtualMachineDeployments()->save($vmDeployment);

        $deleteSshKeyActionMock = self::createMock(DeleteSshKeyAction::class);
        $deleteSshKeyActionMock->expects(self::once())
            ->method('execute')
            ->willReturn(false);

        $this->app->bind(DeleteSshKeyAction::class, fn (): DeleteSshKeyAction =>  $deleteSshKeyActionMock);

        $this->actingAsCustomer($this->customer)->deleteJson(
            $this->generateRoute('partners.cloudstack.ssh-key.destroy', ['sshKey' => $sshKey->uuid])
        )->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Test]
    public function deleteEndpointKeyNotFound(): void
    {
        $this->actingAsCustomer($this->customer)->deleteJson(
            $this->generateRoute('partners.cloudstack.ssh-key.destroy', ['sshKey' => 'c45e24ea-1019-44ab-93b1-a3e96e4e7752'])
        )->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}
