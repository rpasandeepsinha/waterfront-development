<?php

declare(strict_types=1);

namespace Waterfront\Domain\Invoices\DTO;

use Carbon\CarbonImmutable;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Webmozart\Assert\Assert;

class OneTimeServiceContext
{
    public function __construct(
        public Subscription $subscription,
        public Product $product,
        public int $amount,
        public int $discountPercentage,
        public OneTimeServiceStatus $status,
        public CarbonImmutable $executionDate,
        public ?string $comment,
        public ?int $grossPrice,
    ) {
        Assert::same(
            $this->product->productGroup->slug,
            ProductGroupType::ONE_TIME_SERVICE,
        );
        Assert::greaterThan($this->amount, 0);
        Assert::range($this->discountPercentage, 0, 100);
    }
}
