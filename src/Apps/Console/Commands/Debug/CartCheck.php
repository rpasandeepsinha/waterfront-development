<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\Debug;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Ramsey\Uuid\Rfc4122\UuidV4;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use SandwaveIo\LighthouseAuthBase\Identity\Metadata\MetadataPublic;
use SandwaveIo\LighthouseAuthBase\Identity\Traits;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use stdClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Apps\API\Waterfront\Controllers\CartController;
use Waterfront\Apps\API\Waterfront\Requests\Cart\CartCheckRequest;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;
use Waterfront\Support\Enums\Environment;
use Webmozart\Assert\Assert;

#[AsCommand(name: 'debug:cart-check')]
#[Description('Output all prices that the cart check endpoint would return for a payload')]
#[Signature('debug:cart-check {payload}')]
class CartCheck extends Command
{
    public function handle(
        Environment $environment,
    ): int {
        Assert::same($environment, Environment::DEV, 'This command should only run on a dev environment');

        $application = Container::getInstance();

        /** @var string $payload */
        $payload = $this->argument('payload');

        $authenticationManagerFaker = new class () extends AuthenticationManager {
            public function __construct()
            {
            }

            public function setSubject(AuthenticatedCustomer $customer): void
            {
                $this->authenticatedSubject = $customer;
            }
        };
        $authenticationManager = new $authenticationManagerFaker();
        $authenticationManager->setSubject(new AuthenticatedCustomer(
            $this->findOrCreateCustomer(),
            new KratosIdentity(
                UuidV4::uuid4(),
                SchemaId::CUSTOMER,
                'active',
                null,
                new Traits('some@email.address', null),
                null,
                null,
                null,
                null,
                null,
                new MetadataPublic(null, null, [], null, null, [Permissions::RUN_ONE_OFF_SCRIPT->value], null),
                null,
                null,
            ),
            true
        ));

        $application->bind(AuthenticationManager::class, fn () => $authenticationManager);
        $cartController = $application->make(CartController::class);

        $httpRequest = CartCheckRequest::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $payload);
        $responseContent = (string) $cartController->calculateCart($httpRequest)->getContent();
        $response = json_decode($responseContent, false, 512, JSON_THROW_ON_ERROR);
        assert($response instanceof stdClass);

        $this->table(
            ['Slug', 'Billing', 'Contract', 'Regular price', 'Applied price'],
            array_map(fn (stdClass $item) => [
                $item->productSlug,
                $item->billingPeriod,
                $item->contractPeriod,
                $item->price->regularPrice->priceExclVat,
                $item->price->appliedPrice->priceExclVat,
            ], $response->items),
        );

        return self::SUCCESS;
    }

    private function findOrCreateCustomer(): Customer
    {
        $customer = Customer::first();

        $customer ??= new Customer();

        return $customer;
    }
}
