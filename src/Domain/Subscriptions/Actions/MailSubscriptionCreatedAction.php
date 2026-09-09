<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Actions;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCreated;
use Waterfront\Support\Config\ApplicationConfig;
use Waterfront\Support\Helpers\Money;
use Webmozart\Assert\Assert;

class MailSubscriptionCreatedAction
{
    public function __construct(private readonly MailerInterface $mailer, private readonly ApplicationConfig $applicationConfig)
    {
    }

    /**
     * @param mixed[] $data
     */
    public function execute(Customer $customer, array $data): void
    {
        $totalPrice = $this->getOrderTotalPrice($data);
        $preparedData = $this->prepareData($data);

        $vatRate = $this->applicationConfig->defaultTaxRate;

        $this->mailer->send(
            recipients: [$customer],
            template: new MailSubscriptionCreated(
                order: $preparedData,
                total: Money::format($totalPrice),
                totalVat: Money::format((int) round($totalPrice * (1 + ($vatRate / 100)))),
            ),
        );
    }

    /**
     * @param mixed[] $data
     *
     * @return mixed[]
     */
    private function prepareData(array $data): array
    {
        $preparedData = [];
        Assert::isIterable($data['subscriptions']);

        foreach ($data['subscriptions'] as $type => $products) {
            $preparedProducts = [];

            foreach ($products as $product) {
                $preparedProducts[] = $this->prepareProduct($product);
            }

            $preparedData[$type] = $preparedProducts;
        }

        return $preparedData;
    }

    /**
     * @param array<string, mixed> $product
     *
     * @return array<string, mixed>
     */
    private function prepareProduct(array $product): array
    {
        $contractPeriod = $product['contract_period'];
        Assert::integer($contractPeriod);

        $product['period_unit'] = $contractPeriod === 1 ? 'month' : 'months';

        if ($contractPeriod % 12 === 0) {
            $contractPeriod = intdiv($contractPeriod, 12);

            $product['contract_period'] = $contractPeriod;
            $product['period_unit'] = $contractPeriod === 1 ? 'year' : 'years';
        }

        assert(is_numeric($product['price']));
        $product['price'] = Money::format($product['price']);

        return $product;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function getOrderTotalPrice(array $data): int
    {
        $total = 0;

        assert(is_iterable($data['subscriptions']));
        foreach ($data['subscriptions'] as $products) {
            $total += (int) array_reduce($products, fn ($total, $product) => (int) $total + (int) $product['price']);
        }

        return $total;
    }
}
