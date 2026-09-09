<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\DirectAdmin;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Waterfront\Infra\DirectAdminClient\DirectAdmin\Package;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminFieldException;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminPackagNotFoundException;

#[CoversClass(Package::class)]
class PackageTest extends DirectAdminTestCase
{
    private DirectAdminApi $api;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $server = $this->getTestServer();
        $this->api = new DirectAdminApi($server);
    }

    protected function tearDown(): void
    {
        $this->deleteTestPackages(['my-package', 'leettest', '1', '2', '3']);
        parent::tearDown();
    }

    #[Test]
    public function oneOrMorePackagesCanBeDeleted(): void
    {
        $this->createTestPackage('1');
        $this->createTestPackage('2');
        $this->createTestPackage('3');

        $responseDeleted = 'error=0&text=Deleted&details=';
        $responseFirstDelete = '["2","3"]';
        $responseSecondDelete = '[""]';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $responseDeleted),
            new Response(200, $this->getDefaultResponseHeaders(), $responseFirstDelete),
            new Response(200, $this->getDefaultResponseHeaders(), $responseDeleted),
            new Response(200, $this->getDefaultResponseHeaders(), $responseSecondDelete),
            new Response(200, $this->getDefaultResponseHeaders(), $responseSecondDelete),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);
        $this->package = new Package($this->api);

        $this->package->delete(['1']);

        Assert::assertNotContains('1', $this->package->all());

        $this->package->delete(['2', '3']);

        Assert::assertNotContains('2', $this->package->all());
        Assert::assertNotContains('3', $this->package->all());
    }

    #[Test]
    public function packagesCanBeRetrieved(): void
    {
        $this->createTestPackage('1');
        $this->createTestPackage('2');
        $this->createTestPackage('3');

        $response = '["1","2","3"]';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);
        $this->package = new Package($this->api);

        $retrieved = $this->package->all();

        Assert::assertContains('1', $retrieved);
        Assert::assertContains('2', $retrieved);
        Assert::assertContains('3', $retrieved);
    }

    #[Test]
    public function packageCanBeRetrieved(): void
    {
        $this->createTestPackage('1');

        $dnsControl = 'ON';
        $ftp = 'unlimmited';
        $mysql = '5';

        $response = [
            'dnscontrol' => $dnsControl,
            'ftp' => $ftp,
            'mysql' => $mysql,
        ];

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), json_encode($response, JSON_THROW_ON_ERROR)),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);
        $this->package = new Package($this->api);

        $retrieved = $this->package->get('test');

        Assert::assertSame($dnsControl, $retrieved['dnscontrol']);
        Assert::assertSame($ftp, $retrieved['ftp']);
        Assert::assertSame($mysql, $retrieved['mysql']);
    }

    /**
     * @throws DirectAdminFieldException
     */
    #[Test]
    public function packagesCanBeCreatedWithCreateMethod(): void
    {
        $response = '{ "result": "", "success": "Saved"}';
        $responseAll = '["leettest"]';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
            new Response(200, $this->getDefaultResponseHeaders(), $responseAll),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);
        $this->package = new Package($this->api);

        $created = $this->package->create(['bandwidth' => '1337', 'packagename' => 'leettest']);

        Assert::assertTrue($created->hasSucceeded());

        Assert::assertContains('leettest', $this->package->all());
    }

    #[Test]
    public function updatingNonExistingPackageShouldThrowException(): void
    {
        $responseAll = '["different"]';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $responseAll),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);
        $this->package = new Package($this->api);

        $this->expectException(DirectAdminPackagNotFoundException::class);
        $this->package->update('random', ['php' => 'OFF']);
    }

    #[Test]
    public function anExistingPackageCanBeUpdated(): void
    {
        $responseCreated = '{ "result": "", "success": "Saved"}';
        $responseAll = '["leettest"]';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $responseCreated),
            new Response(200, $this->getDefaultResponseHeaders(), $responseAll),
            new Response(200, $this->getDefaultResponseHeaders(), $responseCreated),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);
        $this->package = new Package($this->api);

        $this->package->create(['bandwidth' => '1337', 'packagename' => 'leettest']);

        $updated = $this->package->update('leettest', ['bandwidth' => '1338']);

        Assert::assertTrue($updated->hasSucceeded());
    }

    #[Test]
    public function propertiesCanBeSetDynamicallyFromConstructor(): void
    {
        $packageSettings = [
            'aftp' => 'ON',
            'cgi' => 'ON',
            'dnscontrol' => 'ON',
            'bandwidth' => '1000',
            'domainptr' => '2',
            'ftp' => '2',
            'mysql' => '2',
            'nemailf' => '1',
            'nemailml' => '1',
            'nemailr' => '1',
            'nemails' => '11',
            'nsubdomains' => '15',
            'quota' => '200',
            'skin' => 'enhanced',
            'ssh' => 'ON',
            'ssl' => 'OFF',
            'php' => 'OFF',
            'cron' => 'OFF',
            'spam' => 'OFF',
            'vdomains' => '2',
            'suspend_at_limit' => 'OFF',
            'packagename' => 'my-package',
            'language' => 'nl',
            'sysinfo' => 'OFF',
            'ips' => 2,
            'serverip' => 'OFF',
            'userssh' => 'ON',
            'catchall' => 'OFF',
        ];

        $responseCreated = '{ "result": "", "success": "Saved"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $responseCreated),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $created = new Package($this->api)->create($packageSettings);

        Assert::assertTrue($created->hasSucceeded());
    }
}
