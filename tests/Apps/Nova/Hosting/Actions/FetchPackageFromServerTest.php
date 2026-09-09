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
use Waterfront\Apps\Nova\Hosting\Actions\NovaFetchPackageFromServer;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;

#[CoversClass(NovaFetchPackageFromServer::class)]
class FetchPackageFromServerTest extends IntegrationTestCase
{
    #[Test]
    public function action(): void
    {
        $package = 'brons';
        $server = new ServerFactory()
            ->directadmin()
            ->createOne(['hostname' => 'single-server.nl']);

        ProviderFactory::new()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::DIRECTADMIN, 'enabled' => true, 'default' => true]);

        $action = self::resolve(NovaFetchPackageFromServer::class);

        $fields =  new ActionFields((new Collection(['package' => $package])), (new Collection()));
        $payload = new Collection([$server]);

        $result = $action->handle($fields, $payload);
        self::assertInstanceOf(ActionResponse::class, $result);
        $modal = $result['modal'];
        self::assertInstanceOf(Modal::class, $modal);

        self::assertSame(
            "Fetched package {{$package}} from server with hostname {single-server.nl} with response:",
            $modal->payload['title']
        );
    }
}
