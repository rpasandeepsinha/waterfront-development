<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Rules;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use Tests\Factories\ProductAddonCouplingFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Payments\Services\PaymentService;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Subscriptions\Rules\BillingPeriodRules;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(BillingPeriodRules::class)]
class BillingPeriodRulesTest extends IntegrationTestCase
{
    private AuthenticationManager&MockObject $mockAuthenticationManager;

    private BillingPeriodRules $rule;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockAuthenticationManager = self::createMock(AuthenticationManager::class);

        $productGroup = new ProductGroupFactory()->createOne(['slug' => 'ssl']);
        $this->product = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'ssl-basic',
        ]);
        new ProductPriceComponentFactory()->for($this->product)->registration()->createOne([
            'contract_period' => 12,
            'billing_period' => 12,
        ]);
        new ProductPriceComponentFactory()->for($this->product)->registration()->createOne([
            'contract_period' => 12,
            'billing_period' => 1,
        ]);
        new ProductPriceComponentFactory()->for($this->product)->registration()->createOne([
            'contract_period' => 1,
            'billing_period' => 1,
        ]);

        $this->rule = new BillingPeriodRules(
            self::resolve(TranslatorInterface::class),
            $this->mockAuthenticationManager,
            self::resolve(ProductRepository::class),
            self::resolve(PriceResolver::class),
            self::resolve(PaymentService::class),
        );
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    #[Test]
    public function billingExceedsContract(): void
    {
        $this->mockAuthenticationManager->expects(self::never())
            ->method('getAuthenticatedCustomer');

        $this->rule->setData([
            'subscriptions.ssl.0.slug' => $this->product->slug,
            'subscriptions.ssl.0.status' => 'registration',
            'subscriptions.ssl.0.contract_period' => 1,
            'subscriptions.ssl.0.billing_period' => 12,
        ]);

        $this->rule->validate('subscriptions.ssl.0.billing_period', 12, self::assertClosureIsCalled(
            true,
            'validation.billing_period_exceeds_contract_period',
        ));
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    #[Test]
    public function noMonthlyBilling(): void
    {
        $this->mockAuthenticationManager->expects(self::once())
            ->method('getAuthenticatedCustomer')
            ->willReturn($this->makeAuthenticatedCustomer(false));

        $this->rule->setData([
            'subscriptions.ssl.0.slug' => $this->product->slug,
            'subscriptions.ssl.0.status' => 'registration',
            'subscriptions.ssl.0.contract_period' => 12,
            'subscriptions.ssl.0.billing_period' => 12,
        ]);

        $this->rule->validate('subscriptions.ssl.0.billing_period', 12, self::assertClosureIsCalled(false));
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    #[Test]
    public function monthlyBillingButCustomerHasDirectDebit(): void
    {
        $this->mockAuthenticationManager->expects(self::once())
            ->method('getAuthenticatedCustomer')
            ->willReturn($this->makeAuthenticatedCustomer(true));

        $this->rule->setData([
            'subscriptions.ssl.0.slug' => $this->product->slug,
            'subscriptions.ssl.0.status' => 'registration',
            'subscriptions.ssl.0.contract_period' => 12,
            'subscriptions.ssl.0.billing_period' => 1,
        ]);

        $this->rule->validate('subscriptions.ssl.0.billing_period', 1, self::assertClosureIsCalled(false));
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    #[Test]
    public function monthlyBillingButDirectDebitWillBeCreated(): void
    {
        $this->mockAuthenticationManager->expects(self::once())
            ->method('getAuthenticatedCustomer')
            ->willReturn($this->makeAuthenticatedCustomer(false));

        $this->rule->setData([
            'paymentMethod' => 'ideal',
            'subscriptions.ssl.0.slug' => $this->product->slug,
            'subscriptions.ssl.0.status' => 'registration',
            'subscriptions.ssl.0.contract_period' => 12,
            'subscriptions.ssl.0.billing_period' => 1,
        ]);

        $this->rule->validate('subscriptions.ssl.0.billing_period', 1, self::assertClosureIsCalled(false));
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    #[Test]
    public function monthlyBillingWithNoDirectDebit(): void
    {
        $this->mockAuthenticationManager->expects(self::once())
            ->method('getAuthenticatedCustomer')
            ->willReturn($this->makeAuthenticatedCustomer(false));

        $this->rule->setData([
            'paymentMethod' => 'bancontact',
            'subscriptions.ssl.0.slug' => $this->product->slug,
            'subscriptions.ssl.0.status' => 'registration',
            'subscriptions.ssl.0.contract_period' => 12,
            'subscriptions.ssl.0.billing_period' => 1,
        ]);

        $this->rule->validate('subscriptions.ssl.0.billing_period', 1, self::assertClosureIsCalled(
            true,
            'validation.no_monthly_billing_without_direct_debit',
        ));
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    #[Test]
    public function monthlyBillingWithNoPaymentMethod(): void
    {
        $this->mockAuthenticationManager->expects(self::once())
            ->method('getAuthenticatedCustomer')
            ->willReturn($this->makeAuthenticatedCustomer(false));

        $this->rule->setData([
            'subscriptions.ssl.0.slug' => $this->product->slug,
            'subscriptions.ssl.0.status' => 'registration',
            'subscriptions.ssl.0.contract_period' => 12,
            'subscriptions.ssl.0.billing_period' => 1,
        ]);

        $this->rule->validate('subscriptions.ssl.0.billing_period', 1, self::assertClosureIsCalled(false));
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    #[Test]
    public function monthlyBillingForAddonWithNoDirectDebit(): void
    {
        $parentProduct = new ProductFactory()->for($this->product->productGroup)->createOne();
        new ProductAddonCouplingFactory()->createOne([
            'parent_product_id' => $parentProduct->id,
            'addon_product_id' => $this->product->id,
        ]);

        $this->mockAuthenticationManager->expects(self::once())
            ->method('getAuthenticatedCustomer')
            ->willReturn($this->makeAuthenticatedCustomer(false));

        $this->rule->setData([
            'paymentMethod' => 'bancontact',
            'subscriptions.ssl.0.slug' => $this->product->slug,
            'subscriptions.ssl.0.status' => 'registration',
            'subscriptions.ssl.0.contract_period' => 12,
            'subscriptions.ssl.0.billing_period' => 1,
        ]);

        $this->rule->validate('subscriptions.ssl.0.billing_period', 1, self::assertClosureIsCalled(false));
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    #[Test]
    public function monthlyBillingWithMonthlyContract(): void
    {
        $this->mockAuthenticationManager->expects(self::once())
            ->method('getAuthenticatedCustomer')
            ->willReturn($this->makeAuthenticatedCustomer(false));

        $this->rule->setData([
            'paymentMethod' => 'bancontact',
            'subscriptions.ssl.0.slug' => $this->product->slug,
            'subscriptions.ssl.0.status' => 'registration',
            'subscriptions.ssl.0.contract_period' => 1,
            'subscriptions.ssl.0.billing_period' => 1,
        ]);

        $this->rule->validate('subscriptions.ssl.0.billing_period', 1, self::assertClosureIsCalled(false));
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    #[Test]
    public function monthlyBillingWithNoOtherAvailablePeriods(): void
    {
        $this->mockAuthenticationManager->expects(self::once())
            ->method('getAuthenticatedCustomer')
            ->willReturn($this->makeAuthenticatedCustomer(false));

        ProductPriceComponent::query()
            ->where('product_id', '=', $this->product->id)
            ->where('contract_period', '=', 12)
            ->where('billing_period', '=', 12)
            ->delete();

        $this->rule->setData([
            'paymentMethod' => 'bancontact',
            'subscriptions.ssl.0.slug' => $this->product->slug,
            'subscriptions.ssl.0.status' => 'registration',
            'subscriptions.ssl.0.contract_period' => 12,
            'subscriptions.ssl.0.billing_period' => 1,
        ]);

        $this->rule->validate('subscriptions.ssl.0.billing_period', 1, self::assertClosureIsCalled(false));
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    #[Test]
    public function invalidSlug(): void
    {
        $this->mockAuthenticationManager->expects(self::never())
            ->method('getAuthenticatedCustomer');

        $this->rule->setData([
            'paymentMethod' => 'bancontact',
            'subscriptions.ssl.0.slug' => 'unknown-product',
            'subscriptions.ssl.0.status' => 'registration',
            'subscriptions.ssl.0.contract_period' => 12,
            'subscriptions.ssl.0.billing_period' => 1,
        ]);

        $this->rule->validate('subscriptions.ssl.0.billing_period', 1, self::assertClosureIsCalled(false));
    }

    private function makeAuthenticatedCustomer(bool $hasDirectDebit): object
    {
        $identity = self::createStub(KratosIdentity::class);
        $customer = new Customer();
        $customer->has_direct_debit = $hasDirectDebit;
        return new AuthenticatedCustomer($customer, $identity, true);
    }
}
