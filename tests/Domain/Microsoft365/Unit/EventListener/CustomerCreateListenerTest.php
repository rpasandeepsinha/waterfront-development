<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Unit\EventListener;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use SandwaveIo\Office365\Entity\Customer as KpnCustomer;
use SandwaveIo\Office365\Helper\EntityHelper;
use SandwaveIo\Office365\Library\Observer\Status\Status;
use Tests\Factories\CustomerAddressFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\EventListener\CustomerCreateListener;
use Waterfront\Domain\Microsoft365\Mailer\Microsoft365SignMca;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;

#[CoversClass(CustomerCreateListener::class)]
class CustomerCreateListenerTest extends IntegrationTestCase
{
    private Customer $customer;

    private Microsoft365CustomerInfo $microsoft365CustomerInfo;

    private MailerInterface&MockObject $mockMailerInterface;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $this->customer = new CustomerFactory()->createOne([
            'first_name' => 'Sandwave',
            'last_name' => 'Test',
            'email' => 'test@sandwave.com',
            'phone_country_code' => '+31',
            'phone_area_code' => '61',
            'phone_subscriber_number' => '123123123',
        ]);

        new CustomerAddressFactory()->for($this->customer)->create([
            'street_name' => 'Test straat',
            'street_number' => '13-A',
            'zip_code' => '7777AA',
            'city' => 'Vlissingen',
            'country_code' => 'NL',
        ]);

        $this->microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'technical_status' => Microsoft365ProcessStatus::INITIATED,
        ]);

        Config::set('app.url', 'https://example.com');
        $this->mockMailerInterface = $this->createMock(MailerInterface::class);
        $this->app->bind(MailerInterface::class, fn () => $this->mockMailerInterface);
    }

    #[Test]
    public function createCustomerSuccess(): void
    {
        $this->mockMailerInterface->expects(self::once())
            ->method('send')
            ->with(
                [$this->customer->fresh()],
                new Microsoft365SignMca('https://example.com/microsoft-365')
            );

        $kpnCustomer = $this->createKpnCustomerEntity();

        $status = (new Status('Modified', []));

        self::resolve(CustomerCreateListener::class)->execute($kpnCustomer, $status);

        $customerInfo = Microsoft365CustomerInfo::where(['kpn_customer_id' => $kpnCustomer->getCustomerId()])->firstOrFail();

        self::assertSame($customerInfo->kpn_customer_id, $kpnCustomer->getCustomerId());
        self::assertSame($this->customer->id, (int) $kpnCustomer->getExternalId());
        self::assertSame(
            $this->customer->first_name . ' ' . $this->customer->last_name,
            $kpnCustomer->getName()
        );
    }

    #[Test]
    public function resellerCustomerCreate(): void
    {
        $this->mockMailerInterface->expects(self::once())
            ->method('send')
            ->with(
                [$this->customer->fresh()],
                new Microsoft365SignMca('https://example.com/microsoft-365')
            );

        $this->microsoft365CustomerInfo->tenant_name = '1.onmicrosoft.com';
        $this->microsoft365CustomerInfo->tenant_id = '123456';
        $this->microsoft365CustomerInfo->save();
        $kpnCustomer1 = $this->createKpnCustomerEntity();

        new Microsoft365CustomerInfoFactory()->for($this->microsoft365CustomerInfo->customer)->createOne([
            'technical_status' => Microsoft365ProcessStatus::ACTIVE,
            'kpn_customer_id' => 'CID12345',
            'tenant_name' => 'test1235',
        ]);

        $status = (new Status('Modified', []));

        self::resolve(CustomerCreateListener::class)->execute($kpnCustomer1, $status);

        self::assertCount(2, Microsoft365CustomerInfo::where('customer_id', $this->microsoft365CustomerInfo->customer->id)->get());
        self::assertCount(
            1,
            Microsoft365CustomerInfo::where('customer_id', $this->customer->id)
            ->where('tenant_name', $this->microsoft365CustomerInfo->tenant_name)
            ->where('technical_status', Microsoft365ProcessStatus::CUSTOMER_CREATED)
            ->get()
        );
    }

    private function createKpnCustomerEntity(): KpnCustomer
    {
        self::assertInstanceOf(CustomerAddress::class, $this->customer->address);
        preg_match('/([0-9]{0,})(.{0,})/', $this->customer->address->street_number, $addressParts);
        self::assertCount(3, $addressParts);
        self::assertInstanceOf(CustomerAddress::class, $this->customer->address);

        $kpnCustomer = EntityHelper::deserializeArray(KpnCustomer::class, [
            'CustomerId' => $this->microsoft365CustomerInfo->kpn_customer_id,
            'Name' => $this->customer->first_name . ' ' . $this->customer->last_name,
            'Street' => $this->customer->address->street_name,
            'HouseNr' => $addressParts[0],
            'HouseNrExtension' => $addressParts[1],
            'ZipCode' => $this->customer->address->zip_code,
            'City' => $this->customer->address->city,
            'CountryCode' => $this->customer->address->country_code,
            'Phone1' => $this->customer->phone_country_code . $this->customer->phone_area_code . $this->customer->phone_subscriber_number,
            'Phone2' => '',
            'Fax' => '',
            'Website' => '',
            'DebitNr' => '',
            'IBAN' => '',
            'BIC' => '',
            'VATNr' => '',
            'LegalStatus' => '',
            'ExternalId' => $this->customer->id,
            'ChamberOfCommerceNr' => '',
            'Header' => [
                'PartnerReference' => 'WF-CUSTOMER-' . $this->customer->id . '-' . $this->microsoft365CustomerInfo->id,
            ],
        ]);

        self::assertInstanceOf(KpnCustomer::class, $kpnCustomer);

        return $kpnCustomer;
    }
}
