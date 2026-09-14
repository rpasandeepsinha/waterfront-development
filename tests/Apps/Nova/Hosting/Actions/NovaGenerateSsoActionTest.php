<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Hosting\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Message;
use Laravel\Nova\Actions\Responses\Redirect;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ResellerHostingDeploymentFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Hosting\Actions\NovaGenerateSsoAction;
use Waterfront\Domain\Hosting\Actions\GetSsoUrlAction;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\MailManagement\Services\MailManagementService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(NovaGenerateSsoAction::class)]
#[AllowMockObjectsWithoutExpectations]
class NovaGenerateSsoActionTest extends IntegrationTestCase
{
    public const string SSO_URL = 'sandwave.io/sso';

    private Subscription $subscription;

    private HostingDeployment $hostingDeployment;

    private HostingService&MockObject $hostingService;

    private MailManagementService&MockObject $mailOnlyService;

    private GetSsoUrlAction&MockObject $getSsoUrlAction;

    private NovaGenerateSsoAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $hostingProduct = new ProductFactory()->for(new ProductGroupFactory()->hosting())->createOne();

        $this->subscription = new SubscriptionFactory()
            ->for(
                $hostingProduct,
            )
            ->for(new CustomerFactory())
            ->createOne();

        $this->hostingDeployment = new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
        ]);

        $this->hostingService = self::createMock(HostingService::class);
        $this->mailOnlyService = self::createMock(MailManagementService::class);
        $this->getSsoUrlAction = self::createMock(GetSsoUrlAction::class);

        $this->action = new NovaGenerateSsoAction(
            self::resolve(TranslatorInterface::class),
            $this->hostingService,
            $this->mailOnlyService,
            $this->getSsoUrlAction,
        );
    }

    #[Test]
    public function noProvider(): void
    {
        $result = $this->action->handle($this->getFields([]), new Collection([$this->hostingDeployment]));

        self::assertInstanceOf(ActionResponse::class, $result);
        $danger = $result['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('nova.general_actions_relevant_provider_hosting_not_found', $danger->text);
    }

    #[Test]
    public function hostingServiceSsoEmpty(): void
    {
        $provider = new ProviderFactory()->hostingDirectAdmin()->createOne();
        $this->hostingDeployment->provider()->associate($provider);
        $this->hostingDeployment->save();

        $this->hostingService
            ->expects(self::once())
            ->method('getSsoUrl')
            ->with($this->hostingDeployment, '127.0.0.1', false)
            ->willReturn('');

        $result = $this->action->handle($this->getFields([]), new Collection([$this->hostingDeployment]));

        self::assertInstanceOf(ActionResponse::class, $result);
        $danger = $result['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('nova-action.generate_sso_action_failed', $danger->text);
    }

    #[Test]
    public function hostingServiceSso(): void
    {
        $provider = new ProviderFactory()->hostingDirectAdmin()->createOne();
        $server = new ServerFactory()->directadmin()->createOne();
        $this->hostingDeployment->provider()->associate($provider);
        $this->hostingDeployment->server()->associate($server);
        $this->hostingDeployment->save();

        $this->hostingService
            ->expects(self::once())
            ->method('getSsoUrl')
            ->with($this->hostingDeployment, '127.0.0.1', false)
            ->willReturn(self::SSO_URL);

        $result = $this->action->handle($this->getFields([]), new Collection([$this->hostingDeployment]));

        self::assertInstanceOf(ActionResponse::class, $result);
        $redirect = $result['redirect'];
        self::assertInstanceOf(Redirect::class, $redirect);
        self::assertTrue($redirect->openInNewTab);
        self::assertSame(self::SSO_URL, $redirect->url);
    }

    #[Test]
    public function spamExpertsSso(): void
    {
        $provider = new ProviderFactory()->emailOnlyDirectAdmin()->createOne();
        $server = new ServerFactory()->directadminMail()->createOne();
        $this->hostingDeployment->mailProvider()->associate($provider);
        $this->hostingDeployment->mailOnlyServer()->associate($server);
        $this->hostingDeployment->save();

        $this->mailOnlyService
            ->expects(self::once())
            ->method('spamExpertsSso')
            ->with($this->hostingDeployment->subscription)
            ->willReturn(self::SSO_URL);

        $result = $this->action->handle($this->getFields([]), new Collection([$this->hostingDeployment]));

        self::assertInstanceOf(ActionResponse::class, $result);
        $redirect = $result['redirect'];
        self::assertInstanceOf(Redirect::class, $redirect);
        self::assertTrue($redirect->openInNewTab);
        self::assertSame(self::SSO_URL, $redirect->url);
    }

    #[Test]
    public function getSsoUrlActionWithReseller(): void
    {
        $provider = new ProviderFactory()->hostingDirectAdmin()->createOne();

        $resellerDeployment = new ResellerHostingDeploymentFactory()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'provider_id' => $provider->id,
        ]);

        $this->getSsoUrlAction
            ->expects(self::once())
            ->method('execute')
            ->with($resellerDeployment->server, $resellerDeployment->getRelevantUsernameAttribute(), '127.0.0.1')
            ->willReturn(self::SSO_URL);

        $result = $this->action->handle($this->getFields([]), new Collection([$resellerDeployment])); // @phpstan-ignore-line can be resellerdeployment

        self::assertInstanceOf(ActionResponse::class, $result);
        $redirect = $result['redirect'];
        self::assertInstanceOf(Redirect::class, $redirect);
        self::assertTrue($redirect->openInNewTab);
        self::assertSame(self::SSO_URL, $redirect->url);
    }

    /**
     * @param array<string, bool|null|string> $payload
     */
    private function getFields(array $payload): ActionFields
    {
        return new ActionFields(new Collection($payload), new Collection([]));
    }
}
