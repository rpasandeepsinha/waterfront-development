<?php

declare(strict_types=1);

namespace Tests\Apps\API\Atlantis\Controllers\OrderControllerTest;

use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;
use Waterfront\Domain\Hosting\DirectAdmin\Services\DirectadminUsernameBroker;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\DirectAdminClient\BehavesAsDirectAdmin;
use Waterfront\Infra\DirectAdminClient\Commands\Users\CreateUser;
use Waterfront\Infra\DirectAdminClient\DirectAdmin;
use Waterfront\Infra\DirectAdminClient\DirectAdmin\User as DaUser;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\DirectAdminApiInterface;

#[CoversClass(OrderController::class)]
class OrderHostingTest extends IntegrationTestCase
{
    #[Test]
    public function orderHostingWithoutDomainDirectAdmin(): void
    {
        $daUsername = Str::random(10);
        $daServer = new ServerFactory()
            ->directadmin()
            ->createOne(['hostname' => 'single-server.nl']);
        $expectedDomain = $daUsername . '.com';

        new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);
        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();

        $hostingProduct = new ProductFactory()->createOne([
            'slug' => 'hosting_test',
            'name' => 'hosting_test',
            'product_group_id' => $hostingGroup->id,
        ]);

        new ProductPriceComponentFactory()
            ->registration()
            ->createOne(['product_id' => $hostingProduct->id]);

        new ProductSpecFactory()->createOne(['product_id' => $hostingProduct->id]);

        $directAdminMock = self::createMock(DirectAdmin::class);
        $directAdminApiMock = self::createStub(DirectAdminApi::class);
        $directAdminUserMock = self::createMock(DaUser::class);
        $usernameBroker = self::createMock(DirectadminUsernameBroker::class);

        $this->app->bind(DirectAdminApiInterface::class, fn () => $directAdminApiMock);
        $this->app->bind(DirectAdminApiInterface::class, fn () => $directAdminApiMock);
        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $directAdminMock);
        $this->app->bind(DirectadminUsernameBroker::class, fn () => $usernameBroker);

        $usernameBroker->expects(self::once())->method('generateUsername')->willReturn($daUsername);

        $customer = new CustomerFactory()->withAddress()->createOne();

        /** @var array<mixed, mixed> $orderData */
        $orderData = json_decode(
            (string) file_get_contents(__DIR__ . '/data/order_payload_hosting_without_domainname.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $directAdminMock
            ->expects(self::once())
            ->method('user')
            ->with(self::callback(
                fn (Server $server): bool => $server->name === $daServer->name && $server->id === $daServer->id,
            ))
            ->willReturn($directAdminUserMock);

        $createUserCommand = new CreateUser();
        $createUserCommand->setSucceeded(true);

        $directAdminUserMock
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(fn (array $userData): bool => $expectedDomain === $userData['domain']))
            ->willReturn($createUserCommand);

        $response = $this->actingAsCustomer($customer)->json(
            'post',
            $this->generateRoute('partners.order.order'),
            $orderData,
        );

        $response->assertOk();
        $response->assertJsonFragment([
            'status' => 'ok',
        ]);
    }
}
