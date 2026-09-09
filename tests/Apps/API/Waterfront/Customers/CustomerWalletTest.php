<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Customers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\CustomerWalletFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\CustomerWalletController;
use Waterfront\Domain\Customers\Mailers\CustomerWalletRefundRequested;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Mailer\Mailer;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(CustomerWalletController::class)]
class CustomerWalletTest extends IntegrationTestCase
{
    private const string VALID_IBAN_FOREIGN = 'IE64IRCE92050112345678';
    private const string VALID_IBAN_NL = 'NL02ABNA0123456789';
    private const string INVALID_IBAN_NL = 'NL02ABNA0123656789';
    private const string INVALID_ACCOUNT_NUMBER_CHARS_EXCEPT_DOT = '`~!@#$%^&*()_+={}|[]\:;<,>?';

    private Customer $customer;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function showNoWallet(): void
    {
        $this
            ->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.customer-wallet.show', [
                    'customer' => $this->customer->id,
                ])
            )
            ->assertOk()
            ->assertJsonFragment(['data' => []]);
    }

    #[Test]
    public function showNotOwnedErrorNotFound(): void
    {
        $otherCustomer = new CustomerFactory()->createOne();
        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.customer-wallet.show', [
                    'customer' => $otherCustomer->id,
                ])
            )
            ->assertForbidden();
    }

    #[Test]
    public function showWallet(): void
    {
        new CustomerWalletFactory()->create(
            ['customer_id' => $this->customer->customer()]
        );

        $this
            ->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.customer-wallet.show', [
                    'customer' => $this->customer,
                ])
            )
            ->assertOk()
            ->assertJson(
                fn (AssertableJson $json) =>
                $json->hasAll(
                    [
                    'data',
                    'data.id',
                    'data.customer_id',
                    'data.amount',
                    'data.bank_account_name',
                    'data.refund_requested_at',
                    'data.csv_downloaded_at',
                    'data.created_at',
                    ]
                )
                ->missingAll(['message'])
                ->whereAllType(
                    [
                        'data' => 'array',
                        'data.id' => 'integer',
                        'data.customer_id' => 'integer',
                        'data.amount' => 'integer',
                        'data.bank_account_name' => 'string|null',
                        'data.refund_requested_at' => 'string|null',
                        'data.csv_downloaded_at' => 'string|null',
                        'data.created_at' => 'string|null',
                    ]
                )
            );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function invalidAccountNumber(): array
    {
        $invalidAccountNumbers = [];
        $invalidChars = str_split(self::INVALID_ACCOUNT_NUMBER_CHARS_EXCEPT_DOT);
        foreach ($invalidChars as $char) {
            $invalidAccountNumbers[sprintf('invalid char %s', $char)] = [sprintf('anyone containing %s', $char), $char];
        }
        return $invalidAccountNumbers;
    }

    #[DataProvider('invalidAccountNumber')]
    #[Test]
    public function requestRefundInvalidAccountNumber(string $invalidAccountNumber, string $char): void
    {
        $this
            ->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.customer-wallet.request-refund', [
                    'customer' => $this->customer->customer(),
                ]),
                [
                    'bank_account_number' => self::VALID_IBAN_NL,
                    'bank_account_name' => $invalidAccountNumber,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'errors' => [
                    'bank_account_name' => [
                        self::resolve(TranslatorInterface::class)->translate('customer.specialchar-error', ['characters' => $char]),
                    ],
                ],
            ]);
    }

    #[Test]
    public function requestRefundInvalidIban(): void
    {
        $this
            ->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.customer-wallet.request-refund', [
                    'customer' => $this->customer->customer(),
                ]),
                [
                    'bank_account_number' => self::INVALID_IBAN_NL,
                    'bank_account_name' => 'any',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'errors' => [
                    'bank_account_number' => [
                        self::resolve(TranslatorInterface::class)->translate('validation.iban_number'),
                    ],
                ],
            ]);
    }

    #[Test]
    public function requestRefundMissingParams(): void
    {
        $this
            ->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.customer-wallet.request-refund', [
                    'customer' => $this->customer->customer(),
                ])
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
            'errors' => [
                'bank_account_name' => [
                    self::resolve(TranslatorInterface::class)->translate('validation.required'),
                ],
                'bank_account_number' => [
                    self::resolve(TranslatorInterface::class)->translate('validation.required'),
                ],
            ],
        ]);
    }

    #[Test]
    public function requestRefundNoWallet(): void
    {
        $this
            ->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.customer-wallet.request-refund', [
                    'customer' => $this->customer->customer(),
                ]),
                [
                'bank_account_number' => self::VALID_IBAN_NL,
                'bank_account_name' => 'any',
                ]
            )
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertJsonFragment(['message' => 'no wallet found']);
    }

    #[Test]
    public function requestRefundAlreadyRequested(): void
    {
        new CustomerWalletFactory()->create(
            [
                'refund_requested_at' => CarbonImmutable::now(),
                'customer_id' => $this->customer->customer(),
            ]
        );
        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.customer-wallet.request-refund', [
                    'customer' => $this->customer->customer(),
                ]),
                [
                    'bank_account_number' => self::VALID_IBAN_NL,
                    'bank_account_name' => 'any',
                ]
            )
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertJsonFragment(['message' => 'already requested']);
    }

    #[Test]
    public function requestRefund(): void
    {
        new TemplateFactory()->createOne(
            [
                'slug' => CustomerWalletRefundRequested::getTemplateSlug(),
            ]
        );

        new CustomerWalletFactory()->create([
            'refund_requested_at' => null,
            'customer_id' => $this->customer->customer(),
        ]);

        $mailer = self::createMock(Mailer::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(
                self::anything(),
                self::isInstanceOf(CustomerWalletRefundRequested::class),
                self::anything(),
            );
        $this->app->bind(Mailer::class, fn () => $mailer);

        //Before refund
        $this
            ->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.customer-wallet.show', [
                    'customer' => $this->customer,
                ])
            )
            ->assertOk()
            ->assertJson(
                fn (AssertableJson $json) =>
                $json
                    ->has('data.refund_requested_at')
                    ->missingAll(['message'])
                    ->whereType('data.refund_requested_at', 'null')
            );

        //refunding
        $this
            ->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute(
                    'partners.customer-wallet.request-refund',
                    [$this->customer->id]
                ),
                [
                'bank_account_number' => self::VALID_IBAN_FOREIGN,
                'bank_account_name' => 'any',
                ]
            )
            ->assertNoContent();

        //after refunding
        $this
            ->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.customer-wallet.show', [
                    'customer' => $this->customer,
                ])
            )
            ->assertOk()
            ->assertJson(
                fn (AssertableJson $json) =>
                $json
                    ->has('data.refund_requested_at')
                    ->missingAll(['message'])
                    ->whereType('data.refund_requested_at', 'string')
            );
    }

    #[Test]
    public function requestRefundUserWithoutCustomerError(): void
    {
        // user has no customers
        $customer = new CustomerFactory()->createOne();
        $this
            ->actingAsCustomer($customer)
            ->postJson(
                $this->generateRoute('partners.customer-wallet.request-refund', [
                    'customer' => 99999999,
                ]),
                [
                    'bank_account_number' => self::VALID_IBAN_NL,
                    'bank_account_name' => 'any .person',
                ]
            )
            ->assertNotFound();
    }
}
