<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Migrations\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Migrations\Actions\NovaMigratedCustomerReplaceReferenceAction;
use Waterfront\Domain\Customers\Models\MigratedCustomer;

#[CoversClass(NovaMigratedCustomerReplaceReferenceAction::class)]
class ChangeMigratedCustomersReferenceTest extends IntegrationTestCase
{
    private MigratedCustomer $migratedCustomer1;

    private MigratedCustomer $migratedCustomer2;

    private MigratedCustomer $migratedCustomer3;

    protected function setUp(): void
    {
        parent::setUp();

        $customer1 = CustomerFactory::new()->createOne();
        $this->migratedCustomer1 = MigratedCustomersFactory::new()
            ->createOne([
                'administrative_successful' => false,
                'enable_invoicing' => false,
                'successful' => false,
                'reference_name' => 'testUnit',
                'reference_customer_number' => '123',
            ]);
        $this->migratedCustomer1->customers()->attach($customer1->id);

        $customer2 = CustomerFactory::new()->createOne();
        $this->migratedCustomer2 = MigratedCustomersFactory::new()
            ->createOne([
                'administrative_successful' => true,
                'enable_invoicing' => true,
                'successful' => true,
                'reference_name' => 'testUnit',
                'reference_customer_number' => '456',
            ]);
        $this->migratedCustomer2->customers()->attach($customer2->id);

        $customer3 = CustomerFactory::new()->createOne();
        $this->migratedCustomer3 = MigratedCustomersFactory::new()
            ->createOne([
                'administrative_successful' => false,
                'enable_invoicing' => false,
                'successful' => true,
                'reference_name' => 'dontChange',
                'reference_customer_number' => '123',
            ]);
        $this->migratedCustomer3->customers()->attach($customer3->id);
    }

    #[Test]
    public function action(): void
    {
        Event::fake();

        $payload = $this->getTestActionFields(__DIR__ . '/data/migrated_reference_payload.csv');

        $action = self::resolve(NovaMigratedCustomerReplaceReferenceAction::class);
        $action->handle($payload, new Collection());

        $refreshed1 = $this->migratedCustomer1->refresh();
        $refreshed2 = $this->migratedCustomer2->refresh();
        $refreshed3 = $this->migratedCustomer3->refresh();

        self::assertSame('abc', $refreshed1->reference_customer_number);
        self::assertSame('def', $refreshed2->reference_customer_number);
        self::assertSame('123', $refreshed3->reference_customer_number);
    }

    private function getTestActionFields(string $fileLocation): ActionFields
    {
        $csv = (string) file_get_contents($fileLocation);

        $payload = [
            'csv_upload' => UploadedFile::fake()
                ->createWithContent(
                    'server_import.csv',
                    $csv,
                ),
            'business_unit' => 'testUnit',
        ];

        return new ActionFields(new Collection($payload), new Collection([]));
    }
}
