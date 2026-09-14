<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\DTO;

use ArrayIterator;
use IteratorAggregate;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Traversable;
use Waterfront\Domain\Orders\DTO\CartOrderLines\ExtensionLineItem;
use Waterfront\Domain\Orders\DTO\CartOrderLines\GenericLineItem;
use Waterfront\Domain\Orders\DTO\CartOrderLines\LineItem;
use Waterfront\Domain\Orders\DTO\CartOrderLines\Microsoft365LineItem;
use Waterfront\Domain\Orders\DTO\CartOrderLines\RedirectLineItem;
use Waterfront\Domain\Orders\DTO\CartOrderLines\VpsLineItem;
use Waterfront\Domain\Orders\DTO\CartOrderLines\VpsOsLineItem;

/**
 * @implements IteratorAggregate<int, LineItem>
 */
readonly class CartOrderSubscription implements IteratorAggregate
{
    /**
     * @param ?GenericLineItem[]      $backup
     * @param ?GenericLineItem[]      $dns
     * @param ?GenericLineItem[]      $ssl
     * @param ?GenericLineItem[]      $hosting
     * @param ?ExtensionLineItem[]    $extension
     * @param ?VpsLineItem[]          $vps
     * @param ?GenericLineItem[]      $other
     * @param ?Microsoft365LineItem[] $microsoft365
     * @param ?GenericLineItem[]      $resellerHosting
     * @param ?GenericLineItem[]      $addOn
     * @param ?VpsOsLineItem[]        $vpsOs
     * @param ?GenericLineItem[]      $manualSubscription
     * @param ?RedirectLineItem[]     $redirect
     */
    public function __construct(
        public ?array $backup,
        public ?array $dns,
        public ?array $ssl,
        public ?array $hosting,
        public ?array $extension,
        public ?array $vps,
        public ?array $other,
        public ?array $redirect,
        #[SerializedName('cloudstack-os')]
        public ?array $vpsOs,
        #[SerializedName('microsoft-365')]
        public ?array $microsoft365,
        #[SerializedName('reseller-hosting')]
        public ?array $resellerHosting,
        #[SerializedName('add-on')]
        public ?array $addOn,
        #[SerializedName('manual-subscription')]
        public ?array $manualSubscription,
    ) {
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator([
            ...($this->backup ?? []),
            ...($this->dns ?? []),
            ...($this->ssl ?? []),
            ...($this->hosting ?? []),
            ...($this->extension ?? []),
            ...($this->vps ?? []),
            ...($this->other ?? []),
            ...($this->redirect ?? []),
            ...($this->microsoft365 ?? []),
            ...($this->resellerHosting ?? []),
            ...($this->addOn ?? []),
            ...($this->vpsOs ?? []),
            ...($this->manualSubscription ?? []),
        ]);
    }
}
