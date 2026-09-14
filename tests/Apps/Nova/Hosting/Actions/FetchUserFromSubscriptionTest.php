<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Hosting\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Modal;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Hosting\Actions\NovaFetchUserFromSubscription;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;

#[CoversClass(NovaFetchUserFromSubscription::class)]
class FetchUserFromSubscriptionTest extends IntegrationTestCase
{
    #[Test]
    public function action(): void
    {
        $product = ProductFactory::new()->hostingBrons()->createOne();
        $subscription = SubscriptionFactory::new()
            ->withCustomer()
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->for($product)
            ->createOne();

        $server = ServerFactory::new()->directadmin()->createOne(['hostname' => 'single-server.nl']);

        $provider = ProviderFactory::new()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);

        $hostingDeployment = HostingDeploymentFactory::new()
            ->for($subscription, 'subscription')
            ->for($server)
            ->for($provider, 'provider')
            ->createOne(['directadmin_customer_username' => 'Omnis qui.']);

        $action = self::resolve(NovaFetchUserFromSubscription::class);

        $fields = new ActionFields(new Collection(), new Collection());
        $payload = new Collection([$hostingDeployment]);

        $result = $action->handle($fields, $payload);
        self::assertInstanceOf(ActionResponse::class, $result);
        $modal = $result['modal'];
        self::assertInstanceOf(Modal::class, $modal);

        self::assertSame(
            'Fetched user {Omnis qui.} from server with hostname {single-server.nl} with response:',
            $modal->payload['title'],
        );
    }
}
