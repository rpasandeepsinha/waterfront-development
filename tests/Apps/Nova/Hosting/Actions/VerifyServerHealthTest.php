<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Hosting\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Modal;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Hosting\Actions\NovaVerifyServerHealthAction;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Servers\Enums\ServerType;

#[CoversClass(NovaVerifyServerHealthAction::class)]
class VerifyServerHealthTest extends IntegrationTestCase
{
    #[Test]
    public function action(): void
    {
        new ServerFactory()
            ->directadmin()
            ->createOne(['hostname' => 'single-server.nl']);
        new ServerFactory()
            ->plesk()
            ->createOne(['hostname' => 'big-server.nl']);

        new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::DIRECTADMIN, 'enabled' => true, 'default' => true]);

        $service = self::createMock(HostingService::class);
        $service->expects(self::exactly(1))
            ->method('getPackagesOnServer')
            ->willReturn([]);

        $this->app->bind(HostingService::class, fn (): HostingService => $service);

        $action = self::resolve(NovaVerifyServerHealthAction::class);

        $fields =  new ActionFields(
            new Collection(['server_type' => ServerType::DIRECTADMIN->value]),
            new Collection()
        );
        $payload = new Collection([]);

        $result = $action->handle($fields, $payload);
        self::assertInstanceOf(ActionResponse::class, $result);
        $modal = $result['modal'];
        self::assertInstanceOf(Modal::class, $modal);

        self::assertSame(
            'Health checking all standard hosting servers with package fetching for Server Type: directadmin in a queued job. Check the log on debug level for the results',
            $modal->payload['title']
        );
    }
}
