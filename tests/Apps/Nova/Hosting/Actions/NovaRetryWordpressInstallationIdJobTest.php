<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Hosting\Actions;

use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Message;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Hosting\Actions\NovaRetryWordpressInstallationIdJobAction;
use Waterfront\Domain\Hosting\Jobs\ReceiveWpInstallationIdJob;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(NovaRetryWordpressInstallationIdJobAction::class)]
class NovaRetryWordpressInstallationIdJobTest extends IntegrationTestCase
{
    private Product $hostingProduct;

    private HostingDeployment $hostingDeployment;

    private Dispatcher&MockObject $dispatcher;

    private LoggerInterface&MockObject $logger;

    private NovaRetryWordpressInstallationIdJobAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = new CustomerFactory()->createOne();

        $this->hostingProduct = new ProductFactory()
            ->for(new ProductGroupFactory()->hosting())
            ->createOne();

        $subscription = new SubscriptionFactory()->for(
            $this->hostingProduct
        )->for($customer)->createOne();

        $this->hostingDeployment = new HostingDeploymentFactory()
            ->for(new ServerFactory()->directadmin()->createOne())
            ->for(ProviderFactory::new()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::DIRECTADMIN, 'enabled' => true, 'default' => true]), 'provider')
            ->createOne([
                'subscription_uuid' => $subscription->uuid,
            ]);

        $this->dispatcher = self::createMock(Dispatcher::class);

        $this->logger = self::createMock(LoggerInterface::class);

        $this->action = new NovaRetryWordpressInstallationIdJobAction(
            self::resolve(TranslatorInterface::class),
            self::resolve(ProductSpecRepository::class),
            $this->dispatcher,
            $this->logger,
            self::resolve(HostingDeploymentRepository::class)
        );
    }

    #[Test]
    public function actionSuccess(): void
    {
        new ProductSpecFactory()->for($this->hostingProduct)->createOne([
            'name' => ProductSpecName::WAIT_FOR_WP_TOOLKIT->value,
            'value' => 1,
        ]);

        $server = $this->hostingDeployment->server;
        self::assertInstanceOf(Server::class, $server);

        $this->dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(new ReceiveWpInstallationIdJob(
                $this->hostingDeployment->subscription->uuid,
                $server
            ));

        $hostingDeploymentCollection = new Collection();
        $hostingDeploymentCollection->add($this->hostingDeployment);

        $this->logger->expects(self::once())
            ->method('debug')
            ->with(
                'Starting ReceiveWpInstallationId job from nova action for domain {domain.name} on server {server.id}.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $this->hostingDeployment->subscription->domain,
                    LoggingContextKeys::SERVER_ID => $server->id,
                ]
            );

        $actionResponse = $this->action->handle($this->getFields([]), $hostingDeploymentCollection);

        self::assertInstanceOf(ActionResponse::class, $actionResponse);
        $message = $actionResponse['message'];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('nova-action.retry-wordpress-installation-id-job-dispatched', $message->text);
    }

    /**
     * @param array<string, bool|null|string> $payload
     */
    private function getFields(array $payload): ActionFields
    {
        return new ActionFields(new Collection($payload), new Collection([]));
    }
}
