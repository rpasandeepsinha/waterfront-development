<?php

declare(strict_types=1);

namespace Tests\Domain\MailManagement\Services\MailManagementService;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\DirectAdmin\Exceptions\EmailForwardException;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\MailManagement\Services\MailManagementService;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Infra\DirectAdminClient\BehavesAsDirectAdmin;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Infra\DirectAdminClient\Commands\EmailForwards\CreateEmailForward;
use Waterfront\Infra\DirectAdminClient\Commands\EmailForwards\DeleteEmailForward;
use Waterfront\Infra\DirectAdminClient\Commands\EmailForwards\ShowEmailForwards;
use Waterfront\Infra\DirectAdminClient\DirectAdmin;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\DTO\DirectAdminEmailForward;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

#[CoversClass(MailManagementService::class)]
class MailOnlyServiceForwardsTest extends IntegrationTestCase
{
    private HostingDeployment $hostingDeployment;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = CustomerFactory::new()->createOne();

        $hostingProductGroup = ProductGroupFactory::new()
            ->hosting()
            ->createOne([
                'name' => 'Hosting',
                'slug' => ProductGroupType::HOSTING,
            ]);

        $mailOnlyProvider = ProviderFactory::new()
            ->createOne([
                'type' => ProviderType::MAILONLY,
                'slug' => ProviderSlug::DIRECTADMIN,
                'enabled' => true,
                'default' => true,
            ]);

        $server = ServerFactory::new()
            ->directadmin()
            ->createOne();

        $product = ProductFactory::new()
            ->mailOnly($hostingProductGroup)
            ->createOne();

        $subscription = SubscriptionFactory::new()
            ->for($customer)
            ->createOne([
                'product_uuid' => $product->uuid,
            ]);

        $this->hostingDeployment =  HostingDeploymentFactory::new()
            ->for($server, 'mailOnlyServer')
            ->for($mailOnlyProvider, 'mailProvider')
            ->createOne([
                'subscription_uuid' => $subscription->uuid,
            ]);
    }

    #[Test]
    public function createForward(): void
    {
        $expectedCommand = new CreateEmailForward();
        $expectedCommand->responseReceived([
            'success' => 'Forwarder created',
        ]);

        $this->directAdminMock($expectedCommand, false);

        $mailOnlyService = self::resolve(MailManagementService::class);
        $resultMultiple = $mailOnlyService->createEmailForward(
            $this->hostingDeployment,
            'source-test',
            [
                'destination@mail.test',
                'another-destination@mail.test',
            ]
        );

        self::assertTrue($resultMultiple);

        $resultSingle = $mailOnlyService->createEmailForward(
            $this->hostingDeployment,
            'source-test',
            [
                'destination@mail.test',
            ]
        );

        self::assertTrue($resultSingle);
    }

    /**
     * @return iterable<array<string, array<string, array<int, string>>|bool>>
     */
    public static function getForwardsDataProvider(): iterable
    {
        yield 'Fetching forwards with success' => [
            'expectedForwards' => [
                'exampleSource' => [
                    'destination@local.test',
                    'another@local.test',
                ],
                'anotherExampleSource' => [
                    'wow@remote.test',
                ],
            ],
            'throwException' => false,
        ];

        yield 'Fetching forwards will throw command Exception' => [
            'expectedForwards' => [],
            'throwException' => true,
        ];
    }

    /**
     * @param array<string, array<int, string>> $expectedForwards
     */
    #[DataProvider('getForwardsDataProvider')]
    #[Test]
    public function getForwards(
        array $expectedForwards,
        bool $throwException,
    ): void {
        $expectedCommand = new ShowEmailForwards();
        $expectedCommand->responseReceived($expectedForwards);

        $this->directAdminMock($expectedCommand, $throwException);

        $mailOnlyService = self::resolve(MailManagementService::class);

        if ($throwException) {
            $this->expectException(EmailForwardException::class);
        }

        $forwards = $mailOnlyService->getEmailForwardsFromDeployment($this->hostingDeployment);

        if (! $throwException) {
            $expectedCount = count($expectedForwards);
            self::assertCount($expectedCount, $forwards);
            self::assertContainsOnlyInstancesOf(DirectAdminEmailForward::class, $forwards);

            foreach ($forwards as $forward) {
                $source = $forward->getSource();

                self::assertArrayHasKey($forward->getSource(), $expectedForwards);
                self::assertSame($expectedForwards[$source], $forward->getDestinations());
            }
        }
    }

    #[Test]
    public function deleteForward(): void
    {
        $expectedCommand = new DeleteEmailForward();
        $expectedCommand->responseReceived([
            'success' => 'Forwarders deleted',
        ]);

        $this->directAdminMock($expectedCommand, false);

        $mailOnlyService = self::resolve(MailManagementService::class);
        $result = $mailOnlyService->deleteEmailForward(
            $this->hostingDeployment,
            'source-test',
        );

        self::assertTrue($result);
    }

    private function directAdminMock(DirectAdminCommand $expectedCommand, bool $throwException): void
    {
        $mockDirectAdminApi = self::createStub(DirectAdminApi::class);
        $mockDirectAdminApi->method('loginAs')->willReturn($mockDirectAdminApi);

        if ($throwException) {
            $mockDirectAdminApi->method('call')->willThrowException(new DirectAdminCommandException());
        } else {
            $mockDirectAdminApi->method('call')->willReturn($expectedCommand);
        }

        $mockDirectAdmin = self::createStub(DirectAdmin::class);
        $mockDirectAdmin->method('useServer')->willReturn($mockDirectAdminApi);

        $this->app->bind(BehavesAsDirectAdmin::class, fn (): BehavesAsDirectAdmin => $mockDirectAdmin);
    }
}
