<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Actions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Subscriptions\Actions\MailSubscriptionCreatedAction;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCreated;
use Waterfront\Support\Config\ApplicationConfig;

#[CoversClass(MailSubscriptionCreatedAction::class)]
class MailSubscriptionCreatedActionTest extends IntegrationTestCase
{
    /**
     * @param int[] $productPrices
     */
    #[DataProvider('getPricesDataProvider')]
    #[Test]
    public function correctRoundingOfPrice(array $productPrices, string $expectedTotal, string $expectedTotalVat): void
    {
        $customer = new CustomerFactory()->makeOne();

        $inputData = [
            'subscriptions' => [],
            'voucher_data' => [
                'voucher_code' => 'CODE12345',
                'amount_claimed' => 1,
            ],
        ];

        foreach ($productPrices as $price) {
            $inputData['subscriptions'][][] = [
                'price' => $price,
                'contract_period' => 12,
            ];
        }

        $mailer = self::createMock(MailerInterface::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(
                [$customer],
                self::callback(function (MailSubscriptionCreated $template) use ($expectedTotal, $expectedTotalVat) {
                    self::assertSame($template->total, '€ ' . $expectedTotal);
                    self::assertSame($template->totalVat, '€ ' . $expectedTotalVat);

                    return true;
                }),
            );

        $action = new MailSubscriptionCreatedAction($mailer, new ApplicationConfig(21));
        $action->execute($customer, $inputData);
    }

    /**
     * @return array<int, array<int, array<int, int>|string>>
     */
    public static function getPricesDataProvider(): array
    {
        return [
            [[49, 1188, 499], '17,36', '21,01'],
            [[11889, 49], '119,38', '144,45'],
        ];
    }
}
