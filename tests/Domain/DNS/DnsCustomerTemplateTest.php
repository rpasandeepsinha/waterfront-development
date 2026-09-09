<?php

declare(strict_types=1);

namespace Tests\Domain\DNS;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplateRecord;

#[CoversClass(DnsCustomerTemplate::class)]
class DnsCustomerTemplateTest extends IntegrationTestCase
{
    #[Test]
    public function templates(): void
    {
        $template = $this->templateData();
        $record = $template->records()->with('template')->firstOrFail();

        $group = new ProductGroupFactory()->hosting()->createOne();
        $product      = new ProductFactory()->for($group)->createOne([
            'name' => 'DNS Templates',
            'slug' => 'dns_templates',
        ]);
        $baseSubscription = new SubscriptionFactory()->withCustomer()->for($product)->createOne();

        $domainDeployment = new DomainDeploymentFactory()->withPlaceholderProvider()->for($baseSubscription)->createOne();
        $template->domainDeployments()->save($domainDeployment);

        self::assertNotEmpty($template->domainDeployments->toArray());
        self::assertNotEmpty($template->records);
        self::assertNotNull($record->customer);

        self::assertSame($template->id, $domainDeployment->template_id);
        self::assertSame('testTemplate', $record->template->name, 'Template name for record was not equal!');
        self::assertSame('Test organization', $template->customer->organization, 'Customer was not set properly set for the template!');

        $testRecords = $template->records->filter(fn (DnsCustomerTemplateRecord $rec) => $rec->name === 'test.mydomain.com');
        self::assertNotEmpty($testRecords, 'Template did not have test.mydomain.com as a record!');
    }

    public function templateData(): DnsCustomerTemplate
    {
        $customer = new CustomerFactory()->createOne([
            'organization' => 'Test organization',
        ]);

        $template = DnsCustomerTemplate::create([
            'name' => 'testTemplate',
            'customer_id' => $customer->id,
        ]);

        $template->records()->saveMany([
            new DnsCustomerTemplateRecord([
                'name' => 'test.mydomain.com',
                'content' => '127.0.0.1',
                'type' => 'A',
                'ttl' => 3600,
            ]),
            new DnsCustomerTemplateRecord([
                'name' => 'bla.test.mydomain.com',
                'content' => 'test.mydomain.com',
                'type' => 'CNAME',
                'ttl' => 600,
            ]),
            new DnsCustomerTemplateRecord([
                'name' => 'bla2.test.mydomain.com',
                'content' => 'ns03.testing.test. domain-admin.testing.test. 1539941638 3600 600 86400 3600',
                'type' => 'SRV',
                'weight' => 2,
                'priority' => 20,
                'ttl' => 600,
            ]),
            new DnsCustomerTemplateRecord([
                'name' => 'mydomain.com',
                'content' => 'v=spf1 include:spf.spamservice.nl mx a ~all',
                'priority' => 20,
                'type' => 'MX',
                'ttl' => 600,
            ]),
            new DnsCustomerTemplateRecord([
                'name' => 'mydomain.com',
                'content' => '0 issue "comodo.com"',
                'type' => 'CAA',
                'ttl' => 600,
            ]),
            new DnsCustomerTemplateRecord([
                'name' => 'mydomain.com',
                'content' => '_25._tcp.mail.one-example.guide 3 1 1 da92d453eed5c0aede4',
                'type' => 'TLSA',
                'ttl' => 600,
            ]),
        ]);

        return $template;
    }
}
