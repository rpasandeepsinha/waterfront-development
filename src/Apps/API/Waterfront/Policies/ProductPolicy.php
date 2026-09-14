<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Policies;

use Illuminate\Auth\Access\AuthorizationException;
use Waterfront\Domain\Hosting\Repositories\HostingProductSpecRepository;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\PublicSuffixList;

class ProductPolicy
{
    public function __construct(
        private readonly PublicSuffixList $pdp,
        private readonly HostingProductSpecRepository $hostingProductSpecRepository,
    ) {
    }

    /**
     * @throws AuthorizationException
     */
    public function assertCanManageWhois(string $domain): void
    {
        $parsedDomain = $this->pdp->getRules()->getICANNDomain($domain);
        $extension = $parsedDomain->suffix();

        $product = Product::where('name', $extension)->first();

        if ($product === null) {
            throw new AuthorizationException();
        }

        $allowWhois = $product
            ->productSpecs()
            ->where(
                [
                    ['name',  'domain.allow_whois'],
                    ['value', '1'],
                ],
            )
            ->exists();

        if (! $allowWhois) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function assertCanCoupleHosting(Subscription $subscription): void
    {
        if (! $this->hostingProductSpecRepository->allowHostingCoupling($subscription->product)) {
            throw new AuthorizationException();
        }
    }
}
