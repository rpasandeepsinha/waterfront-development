<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\Validators;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\Validator;
use Waterfront\Domain\Cart\Validators\Rules\AddonIsCorrectlyAttached;
use Waterfront\Domain\Cart\Validators\Rules\DnsRules;
use Waterfront\Domain\Cart\Validators\Rules\DoesChildProductItemHaveRelationWithParentProductRule;
use Waterfront\Domain\Cart\Validators\Rules\DomainRules;
use Waterfront\Domain\Cart\Validators\Rules\ProductChangeIsAllowed;
use Waterfront\Domain\Cart\Validators\Rules\SslRules;
use Waterfront\Domain\Domains\Rules\DomainHasNoSubdomainRule;
use Waterfront\Domain\Domains\Rules\DomainNameRule;
use Waterfront\Domain\Hosting\Rules\OrderHostingRules;
use Waterfront\Domain\Microsoft365\Rules\Microsoft365Rules;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Redirects\Rules\OrderRedirectRules;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Rules\BillingPeriodRules;
use Waterfront\Domain\Subscriptions\Rules\ManualSubscriptionRules;
use Waterfront\Domain\VPS\Rules\CloudStackVirtualMachineRules;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Translation\TranslatorInterface;

class CartValidatorFactory extends Factory
{
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly ProductRepository $productRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly BillingPeriodRules $billingPeriodRules,
        private readonly DomainRules $domainRules,
        private readonly DomainNameRule $domainNameRule,
        private readonly DomainHasNoSubdomainRule $subDomainNameRule,
        private readonly SslRules $sslRules,
        private readonly DnsRules $dnsRules,
        private readonly OrderHostingRules $orderHostingRules,
        private readonly OrderRedirectRules $orderRedirectRules,
        private readonly CloudStackVirtualMachineRules $cloudStackVirtualMachineRules,
        private readonly ManualSubscriptionRules $manualSubscriptionRules,
        private readonly Microsoft365Rules $microsoft365Rules,
        private readonly TranslatorInterface $wfTranslator,
        private readonly DoesChildProductItemHaveRelationWithParentProductRule $childProductItemHaveRelationWithParentProductRule,
        private readonly AddonIsCorrectlyAttached $addonIsCorrectlyAttached,
        private readonly ProductChangeIsAllowed $productChangeIsAllowed,
        Translator $translator,
        ?Container $container = null,
    ) {
        parent::__construct($translator, $container);
    }

    /**
     * @param mixed[] $data
     * @param mixed[] $rules
     * @param mixed[] $messages
     * @param mixed[] $customAttributes
     */
    protected function resolve(
        array $data,
        array $rules,
        array $messages,
        array $customAttributes,
    ): Validator|CartValidator {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $this->authenticationManager->getAuthenticatedSubject();

        return new CartValidator(
            $customer,
            $this->productRepository,
            $this->subscriptionRepository,
            $this->billingPeriodRules,
            $this->domainRules,
            $this->sslRules,
            $this->dnsRules,
            $this->orderHostingRules,
            $this->orderRedirectRules,
            $this->cloudStackVirtualMachineRules,
            $this->manualSubscriptionRules,
            $this->microsoft365Rules,
            $this->wfTranslator,
            $this->domainNameRule,
            $this->subDomainNameRule,
            $this->childProductItemHaveRelationWithParentProductRule,
            $this->addonIsCorrectlyAttached,
            $this->productChangeIsAllowed,
            $this->translator,
            $data,
            $rules,
            $messages,
            $customAttributes,
        );
    }
}
