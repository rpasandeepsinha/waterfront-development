<?php

declare(strict_types=1);

namespace Tests\Domain\Servers\Models;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ServerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;

#[CoversClass(Server::class)]
class ServerTest extends IntegrationTestCase
{
    private Server $server;

    private Server $presetServer;

    private string $passwordCheck;

    private string $secretCheck;

    private string $loginCheck;

    private string $plainTekstValue;

    private Server $plainServer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->passwordCheck = 'testPassword';
        $this->secretCheck   = 'testSecretKey';
        $this->loginCheck    = 'testLoginKey';

        $this->server = new ServerFactory()->createOne([
           'password' => null,
           'secret_key' => null,
           'loginkey'  => null,
        ]);

        $this->presetServer = new ServerFactory()->createOne([
            'password' => $this->passwordCheck,
            'secret_key' => $this->secretCheck,
            'loginkey'  => $this->loginCheck,
        ]);

        $this->plainTekstValue = 'old_plain_tekst';

        $plainId = DB::table('hosting_servers')->insertGetId([
            'hostname' => 'single-server.nl',
            'domain' => 'single-server.nl',
            'name' => 'username123',
            'username' => 'username123',
            'ipv4' => '62.222.198.186',
            'ipv6' => '0:0:0:0:0:ffff:3ede:c6ba',
            'port' => 2222,
            'owner' => null,
            'allow_new_websites' => true,
            'maximum_websites' => null,
            'type' => ServerType::DIRECTADMIN,
            'use_ssl' => true,
            'password' => $this->plainTekstValue,
            'secret_key' => $this->plainTekstValue,
            'loginkey'  => $this->plainTekstValue,
        ]);

        $this->plainServer = Server::findOrFail($plainId);
    }

    #[Test]
    public function serverPassword(): void
    {
        $baseServer = $this->server;
        self::assertSame('', $baseServer->password);

        $newPass = 'newPassword';

        $baseServer->password = $newPass;
        $baseServer->save();
        $baseServer->refresh();

        self::assertSame($newPass, $baseServer->password);

        self::assertDatabaseMissing(Server::class, ['password' => $newPass]);

        $presetServer = $this->presetServer;
        self::assertSame($this->passwordCheck, $presetServer->getPassword());

        $updatedPass = 'updatedPassword';

        $presetServer->password = $updatedPass;
        $presetServer->save();
        $presetServer->refresh();

        self::assertSame($updatedPass, $presetServer->password);

        self::assertDatabaseMissing(Server::class, ['password' => $updatedPass]);

        self::assertSame($this->plainTekstValue, $this->plainServer->password);
    }

    #[Test]
    public function serverSecretKey(): void
    {
        $baseServer = $this->server;
        self::assertSame('', $baseServer->secret_key);

        $newSecretKey = 'newSecretKey';

        $baseServer->secret_key = $newSecretKey;
        $baseServer->save();
        $baseServer->refresh();

        self::assertSame($newSecretKey, $baseServer->secret_key);

        self::assertDatabaseMissing(Server::class, ['secret_key' => $newSecretKey]);

        $presetServer = $this->presetServer;
        self::assertSame($this->secretCheck, $presetServer->getSecretKey());

        $updatedSecretKey = 'updatedSecretKey';

        $presetServer->secret_key = $updatedSecretKey;
        $presetServer->save();
        $presetServer->refresh();

        self::assertSame($updatedSecretKey, $presetServer->secret_key);

        self::assertDatabaseMissing(Server::class, ['secret_key' => $updatedSecretKey]);

        self::assertSame($this->plainTekstValue, $this->plainServer->secret_key);
    }

    #[Test]
    public function serverLoginKey(): void
    {
        $baseServer = $this->server;
        self::assertSame('', $baseServer->loginkey);

        $newLoginKey = 'newLoginKey';

        $baseServer->loginkey = $newLoginKey;
        $baseServer->save();

        $baseServer->refresh();

        self::assertSame($newLoginKey, $baseServer->loginkey);

        self::assertDatabaseMissing(Server::class, ['loginkey' => $newLoginKey]);

        $presetServer = $this->presetServer;
        self::assertSame($this->loginCheck, $presetServer->getLoginKey());

        $updatedLoginKey = 'updatedLoginKey';

        $presetServer->loginkey = $updatedLoginKey;
        $presetServer->save();
        $presetServer->refresh();

        self::assertSame($updatedLoginKey, $presetServer->loginkey);

        self::assertDatabaseMissing(Server::class, ['loginkey' => $updatedLoginKey]);

        self::assertSame($this->plainTekstValue, $this->plainServer->loginkey);
    }

    #[Test]
    public function creatingEventValueDomainSetsHostname(): void
    {
        $domain = 'onlydomain.nl';

        $data = [
            'hostname' => null,
            'domain' => $domain,
            'password' => 'secret',
            'secret_key' => 'secret',
            'loginkey'  => 'secret',
        ];

        $server = new Server($data);
        $server->save();

        self::assertSame($domain, $server->hostname);
        self::assertDatabaseHas('hosting_servers', ['hostname' => $domain]);
    }

    #[Test]
    public function creatingEventValueHostnameSetsDomain(): void
    {
        $hostname = 'onlyhostname.nl';

        $data = [
            'hostname' => $hostname,
            'domain' => null,
            'password' => 'secret',
            'secret_key' => 'secret',
            'loginkey'  => 'secret',
        ];

        $server = new Server($data);
        $server->save();

        self::assertSame($hostname, $server->domain);
        self::assertDatabaseHas('hosting_servers', ['domain' => $hostname]);
    }

    #[Test]
    public function creatingEventOnlyChangesWhenNull(): void
    {
        $domain = 'domain.nl';
        $hostname = 'hostname.nl';

        $server = new Server([
            'domain' => $domain,
            'hostname' => $hostname,
            'password' => 'secret',
            'secret_key' => 'secret',
            'loginkey'  => 'secret',
        ]);

        $server->save();

        self::assertSame($domain, $server->domain);
        self::assertSame($hostname, $server->hostname);
        self::assertNotSame($server->domain, $server->hostname);
        self::assertDatabaseHas('hosting_servers', ['domain' => $domain, 'hostname' => $hostname]);
    }
}
