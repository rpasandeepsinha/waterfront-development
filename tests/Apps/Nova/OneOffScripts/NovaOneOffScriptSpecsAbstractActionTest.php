<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\OneOffScripts;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\OneOffScripts\DTO\OneTimeActionSpecs;
use Waterfront\Apps\Nova\OneOffScripts\Enums\Environments;
use Waterfront\Apps\Nova\OneOffScripts\Enums\SpecActions;
use Waterfront\Apps\Nova\OneOffScripts\NovaOneOffScriptSpecsAbstractAction;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Infra\Configuration\ConfigurationInterface;

#[CoversClass(NovaOneOffScriptSpecsAbstractAction::class)]
class NovaOneOffScriptSpecsAbstractActionTest extends IntegrationTestCase
{
    private Product $hostingBrons;

    private NovaOneOffScriptSpecsAbstractAction $novaOneOffScriptSpecsAbstractAction;

    public function setUp(): void
    {
        parent::setUp();

        Config::set('app.tenant', Environments::YOURHOSTING_UAT->value);

        $productGroupHosting =  ProductGroupFactory::new()->createOne(['slug' => ProductGroupType::HOSTING]);

        $this->hostingBrons = new ProductFactory()->hostingBrons($productGroupHosting)->createOne();

        $this->novaOneOffScriptSpecsAbstractAction = new class () extends NovaOneOffScriptSpecsAbstractAction {
            public function __construct()
            {
                $logger = Container::getInstance()->make(LoggerInterface::class);
                $configuration = Container::getInstance()->make(ConfigurationInterface::class);
                $productSpecRepository = Container::getInstance()->make(ProductSpecRepository::class);

                parent::__construct($logger, $configuration, $productSpecRepository);
            }

            protected function getSpecs(): array
            {
                $ignoreOneTimeActionSpec = new OneTimeActionSpecs(
                    SpecActions::CREATE,
                    [Environments::VERSIO_UAT],
                    'ignore-needed-for-abstract-class',
                    '1',
                    ['ignore-needed-for-abstract-class']
                );

                return [$ignoreOneTimeActionSpec];
            }

            protected function getOneOffScriptSlug(): string
            {
                return 'test-foo-bar';
            }

            protected function getOneOffScriptTicketUrl(): string
            {
                return 'test-ticket-ref';
            }
        };
    }

    #[Test]
    public function handleSpecSuccessCreate(): void
    {
        $oneOffScriptSpecs = new OneTimeActionSpecs(
            SpecActions::CREATE,
            [Environments::YOURHOSTING_UAT],
            ProductSpecName::PRODUCT_SHOULD_BE_HIDDEN_IN_SHOPPINGCART->value,
            '1',
            ['hosting_brons']
        );

        $result = $this->novaOneOffScriptSpecsAbstractAction->handleSpec([$oneOffScriptSpecs], false);

        self::assertSame('<table class="w-full divide-y divide-gray-100 dark:divide-gray-700"><tr class="text-left px-6 whitespace-nowrap uppercase text-gray-500 text-xxs tracking-wide py-5"><th>Environment</th><th>Spec Name</th><th>Spec Value</th><th>Action</th><th>Product Slug</th><th>Result</th></tr><tr><td>yourhosting-uat</td><td>product.product-should-be-hidden-in-shoppingcart</td><td>1</td><td>create</td><td>hosting_brons</td><td>Created</td></tr></table>', $result);
        self::assertSame($this->hostingBrons->productSpecs()->firstOrFail()->name, ProductSpecName::PRODUCT_SHOULD_BE_HIDDEN_IN_SHOPPINGCART->value);
    }

    #[Test]
    public function handleSpecSuccessDelete(): void
    {
        $oneOffScriptSpecs = new OneTimeActionSpecs(
            SpecActions::DELETE,
            [Environments::YOURHOSTING_UAT],
            ProductSpecName::PRODUCT_SHOULD_BE_HIDDEN_IN_SHOPPINGCART->value,
            '1',
            ['hosting_brons']
        );

        new ProductSpecFactory()->for($this->hostingBrons)->createOne(['name' => ProductSpecName::PRODUCT_SHOULD_BE_HIDDEN_IN_SHOPPINGCART, 'value' => true]);
        self::assertSame(ProductSpecName::PRODUCT_SHOULD_BE_HIDDEN_IN_SHOPPINGCART->value, $this->hostingBrons->productSpecs()->firstOrFail()->name);

        $result = $this->novaOneOffScriptSpecsAbstractAction->handleSpec([$oneOffScriptSpecs], false);

        self::assertSame('<table class="w-full divide-y divide-gray-100 dark:divide-gray-700"><tr class="text-left px-6 whitespace-nowrap uppercase text-gray-500 text-xxs tracking-wide py-5"><th>Environment</th><th>Spec Name</th><th>Spec Value</th><th>Action</th><th>Product Slug</th><th>Result</th></tr><tr><td>yourhosting-uat</td><td>product.product-should-be-hidden-in-shoppingcart</td><td>1</td><td>delete</td><td>hosting_brons</td><td>Deleted</td></tr></table>', $result);
        self::assertNull($this->hostingBrons->productSpecs()->first());
    }

    #[Test]
    public function handleSpecNotCurrentEnvironment(): void
    {
        $oneOffScriptSpecs = new OneTimeActionSpecs(
            SpecActions::CREATE,
            [Environments::VERSIO_UAT],
            ProductSpecName::PRODUCT_SHOULD_BE_HIDDEN_IN_SHOPPINGCART->value,
            '1',
            ['hosting_brons']
        );

        $result = $this->novaOneOffScriptSpecsAbstractAction->handleSpec([$oneOffScriptSpecs], false);

        self::assertSame('<table class="w-full divide-y divide-gray-100 dark:divide-gray-700"><tr class="text-left px-6 whitespace-nowrap uppercase text-gray-500 text-xxs tracking-wide py-5"><th>Environment</th><th>Spec Name</th><th>Spec Value</th><th>Action</th><th>Product Slug</th><th>Result</th></tr><tr><td>versio-uat</td><td>product.product-should-be-hidden-in-shoppingcart</td><td>1</td><td>create</td><td>-</td><td>Environment yourhosting-uat does not match versio-uat</td></tr></table>', $result);
    }

    #[Test]
    public function handleSpecDoesNotExist(): void
    {
        $oneOffScriptSpecs = new OneTimeActionSpecs(
            SpecActions::DELETE,
            [Environments::YOURHOSTING_UAT],
            ProductSpecName::PRODUCT_SHOULD_BE_HIDDEN_IN_SHOPPINGCART->value,
            '1',
            ['hosting_brons']
        );

        $result = $this->novaOneOffScriptSpecsAbstractAction->handleSpec([$oneOffScriptSpecs], false);

        self::assertSame('<table class="w-full divide-y divide-gray-100 dark:divide-gray-700"><tr class="text-left px-6 whitespace-nowrap uppercase text-gray-500 text-xxs tracking-wide py-5"><th>Environment</th><th>Spec Name</th><th>Spec Value</th><th>Action</th><th>Product Slug</th><th>Result</th></tr><tr><td>yourhosting-uat</td><td>product.product-should-be-hidden-in-shoppingcart</td><td>1</td><td>delete</td><td>hosting_brons</td><td>Spec is not present</td></tr></table>', $result);
    }

    #[Test]
    public function handleProductDoesNotExist(): void
    {
        $oneOffScriptSpecs = new OneTimeActionSpecs(
            SpecActions::CREATE,
            [Environments::YOURHOSTING_UAT],
            ProductSpecName::PRODUCT_SHOULD_BE_HIDDEN_IN_SHOPPINGCART->value,
            '1',
            ['hosting_brons']
        );

        $this->hostingBrons->delete();

        $result = $this->novaOneOffScriptSpecsAbstractAction->handleSpec([$oneOffScriptSpecs], false);

        self::assertSame('<table class="w-full divide-y divide-gray-100 dark:divide-gray-700"><tr class="text-left px-6 whitespace-nowrap uppercase text-gray-500 text-xxs tracking-wide py-5"><th>Environment</th><th>Spec Name</th><th>Spec Value</th><th>Action</th><th>Product Slug</th><th>Result</th></tr><tr><td>yourhosting-uat</td><td>product.product-should-be-hidden-in-shoppingcart</td><td>1</td><td>create</td><td>hosting_brons</td><td>Product does not exist</td></tr></table>', $result);
    }

    #[Test]
    public function handleSpecAlreadyExistsOnTheProduct(): void
    {
        $oneOffScriptSpecs = new OneTimeActionSpecs(
            SpecActions::CREATE,
            [Environments::YOURHOSTING_UAT],
            ProductSpecName::PRODUCT_SHOULD_BE_HIDDEN_IN_SHOPPINGCART->value,
            '1',
            ['hosting_brons']
        );

        new ProductSpecFactory()->for($this->hostingBrons)->createOne(['name' => ProductSpecName::PRODUCT_SHOULD_BE_HIDDEN_IN_SHOPPINGCART, 'value' => true]);
        self::assertSame(ProductSpecName::PRODUCT_SHOULD_BE_HIDDEN_IN_SHOPPINGCART->value, $this->hostingBrons->productSpecs()->firstOrFail()->name);

        $result = $this->novaOneOffScriptSpecsAbstractAction->handleSpec([$oneOffScriptSpecs], false);

        self::assertSame('<table class="w-full divide-y divide-gray-100 dark:divide-gray-700"><tr class="text-left px-6 whitespace-nowrap uppercase text-gray-500 text-xxs tracking-wide py-5"><th>Environment</th><th>Spec Name</th><th>Spec Value</th><th>Action</th><th>Product Slug</th><th>Result</th></tr><tr><td>yourhosting-uat</td><td>product.product-should-be-hidden-in-shoppingcart</td><td>1</td><td>create</td><td>hosting_brons</td><td>Spec already exists on the product</td></tr></table>', $result);
    }

    #[Test]
    public function handleSpecDryRun(): void
    {
        $oneOffScriptSpecs = new OneTimeActionSpecs(
            SpecActions::CREATE,
            [Environments::YOURHOSTING_UAT],
            ProductSpecName::PRODUCT_SHOULD_BE_HIDDEN_IN_SHOPPINGCART->value,
            '1',
            ['hosting_brons']
        );

        $result = $this->novaOneOffScriptSpecsAbstractAction->handleSpec([$oneOffScriptSpecs], true);

        self::assertSame('<table class="w-full divide-y divide-gray-100 dark:divide-gray-700"><tr class="text-left px-6 whitespace-nowrap uppercase text-gray-500 text-xxs tracking-wide py-5"><th>Environment</th><th>Spec Name</th><th>Spec Value</th><th>Action</th><th>Product Slug</th><th>Result</th></tr><tr><td>yourhosting-uat</td><td>product.product-should-be-hidden-in-shoppingcart</td><td>1</td><td>create</td><td>hosting_brons</td><td>Created</td></tr></table>', $result);
        self::assertCount(0, $this->hostingBrons->productSpecs);
    }
}
