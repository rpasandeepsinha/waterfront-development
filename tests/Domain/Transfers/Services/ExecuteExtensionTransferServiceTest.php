<?php

declare(strict_types=1);

namespace Tests\Domain\Transfers\Services;

use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Exceptions\TransferException;
use Waterfront\Domain\Transfers\Interfaces\ExecuteExtensionTransferInterface;
use Waterfront\Domain\Transfers\Services\ExecuteExtensionTransferService;

#[CoversClass(ExecuteExtensionTransferService::class)]
class ExecuteExtensionTransferServiceTest extends IntegrationTestCase
{
    private Customer $receiver;

    private Customer $from;

    private Subscription $subscription;

    private ExecuteExtensionTransferInterface $extensionTransferService;

    private DomainDeployment $domainDeployment;

    private string $testDomain = 'test.nl';

    protected function setUp(): void
    {
        parent::setUp();
        $this->extensionTransferService = self::resolve(ExecuteExtensionTransferInterface::class);
    }

    #[Test]
    public function executeExtensionTransferException(): void
    {
        $this->testDomain = 'domain-without-domain-sub.com';

        $this->prepareDomainExtensionTransferTest();

        $this->subscription->uuid = Str::uuid()->toString();
        $this->subscription->save();

        $this->expectException(TransferException::class);

        $this->expectExceptionMessageIs(sprintf(
            'Unable to find domain subscription for domain {%s}.',
            $this->testDomain,
        ));

        $this->extensionTransferService->execute($this->subscription, $this->receiver);
    }

    #[Test]
    public function executeExtensionTransfer(): void
    {
        $this->prepareDomainExtensionTransferTest();

        self::assertNotNull($this->domainDeployment->contactOwner);
        self::assertSame($this->from->id, $this->domainDeployment->contactOwner->customer->id);

        $this->extensionTransferService->execute($this->subscription, $this->receiver);

        $this->domainDeployment = $this->domainDeployment->refresh();

        self::assertNotNull($this->domainDeployment->contactOwner);
        self::assertSame($this->receiver->id, $this->domainDeployment->contactOwner->customer->id);
    }

    private function prepareDomainExtensionTransferTest(): void
    {
        $this->receiver = new CustomerFactory()->withAddress()->createOne();
        $this->from = new CustomerFactory()->withAddress()->createOne();

        $provider = ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);
        new ProductGroupFactory()->createOne(['slug' => 'extension', 'name' => 'extension']);

        $group = ProductGroup::where('slug', 'extension')->firstOrFail();

        $product = new ProductFactory()->createOne([
            'product_group_id' => $group->id,
            'name' => '.nl',
            'slug' => 'extension_nl',
        ]);

        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'domain' => $this->testDomain,
                'product_uuid' => $product->uuid,
                'customer_id' => $this->from->id,
            ]);

        $this->domainDeployment = new DomainDeploymentFactory()->for($provider)->createOne([
            'subscription_uuid' => $this->subscription->uuid,
        ]);

        $contact = new DomainContactFactory()->createOne([
            'customer_id' => $this->from->id,
            'default_owner' => true,
        ]);

        new DomainContactFactory()->createOne([
            'customer_id' => $this->receiver->id,
            'default_owner' => true,
        ]);

        $contact->contactOwnerDomainSubscriptions()->save($this->domainDeployment);
    }
}
