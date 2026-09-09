<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Rules;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

/**
 * When a new hosting subscription is ordered that is linked to an existing
 * domain subscription or ordered along with a new domain then ensure that the
 * domain subscription has/will have a DNS child that allows coupling hosting.
 * E.g. ordering hosting for a domain that only has Parking DNS should not be
 * allowed.
 */
class ValidDnsPresentForNewCoupledHostingRule extends AbstractValidator
{
    private string $message;

    /**
     * @param mixed[] $input
     */
    public function __construct(
        private readonly array $input,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly ProductRepository $productRepository,
        private readonly ProductSpecRepository $productSpecRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        assert(is_string($value));
        $domain = $value;

        /** @var array<array<string, string|mixed[]>> $domainOrderItems */
        $domainOrderItems = Arr::get($this->input, 'subscriptions.extension');
        $domainOrderItem = new Collection($domainOrderItems)
            ->first(fn ($item) => $item['domain'] === $domain);

        $dnsProduct = null;
        if ($domainOrderItem === null) {
            // Order for existing domain
            $subscription = $this->subscriptionRepository->getActiveDnsSubscription($domain);
            $dnsProduct = $subscription->product;
        } else {
            // Order for new domain
            /** @var array<string, string>|null $dnsOrderItem */
            $dnsOrderItem = Arr::get($domainOrderItem, 'children.dns.0');
            if ($dnsOrderItem !== null) {
                try {
                    $dnsProduct = $this->productRepository->findProductBySlug($dnsOrderItem['slug']);
                } catch (ModelNotFoundException) {
                    // @ignoreException
                }
            }
        }

        if ($dnsProduct === null || ! $this->productSpecRepository->booleanSpecificationIsTrue($dnsProduct, ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT)) {
            $this->message = $this->translator->translate('validation.hosting.dns-does-not-allow-hosting', ['domain' => $domain]);
            return false;
        }

        return true;
    }

    protected function message(): string
    {
        return $this->message;
    }
}
