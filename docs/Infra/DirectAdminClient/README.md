# DirectAdminClient

DirectAdminClient used in Waterfront to control and communicate with DirectAdmin servers.

## Index
- [CHANGELOG.md](CHANGELOG.md)

## Usage

You can add your implementations of the `DirectAdminServer` interface to use to create a connection.

```php
<?php
use Waterfront\Infra\DirectAdminClient\Connection\DirectAdminServer

class DirectAdminServerOne implements DirectAdminServer
{
    public function getLoginKey(): string { return 'key';}
    public function getUsername(): string { return 'username';}
    public function getPassword(): string { return 'password';}
    public function usesSsl(): bool { return true; }
    public function getDomain(): string { return 'my-domain.com';}
    public function getPort(): int { return 2222; }
}
```

Then use the API with your DirectAdminServer.
In this example we're using the `ShowAllUsers` command from DirectAdmin.

```php
$server = new DirectAdminServerOne();
$api = new DirectAdminApi($server);

// show users after connected to the server
$cmd = new ShowAllUsers();
$users = $api->call($cmd);

var_dump($users->getUserList());

/**
 *        Example output:
 *        array(4) {
 *                [0] =>
 *          string(6) "client"
 *                [1] =>
 *          string(5) "test1"
 *                [2] =>
 *          string(5) "test2"
 *                [3] =>
 *          string(5) "test3"
 *        }
 */
```

For testing purposes, a "fake" client is provided. If you want to use this client, overwrite the `BehavesAsDirectAdmin` class in a service provider:

```php
    $this->app->singleton(BehavesAsDirectAdmin::class, function () {
        return new \Waterfront\DirectAdminClient\Fakers\DirectAdmin(new Server());
    });
```

## Laravel

The DirectAdminClient has some optimizations for usage with the [Laravel Framework](https://http://laravel.com/).
The `DirectAdminServiceProvider` needs to be registered which allows you to use the `DirectAdmin` facade.

**Examples with Laravel Facade:**
```php

$server1 = Server::whereDomain('server1.directadminserver.com')->first();
$server2 = Server::whereDomain('server2.directadminserver.com')->first();

DirectAdmin::useServer($server1);
$adminLogin = DirectAdmin::getSSO(); // https://server1.directadminserver.com:2222/CMD_LOGIN_URL?hash=uFNGb...

$users =  collect(DirectAdmin::users()->all());
$userLogin = DirectAdmin::getSSO($users->first()); //https://server1.directadminserver.com:2222/CMD_LOGIN_URL?hash=xD2Gb...

DirectAdmin::useServer($server2);
$adminLogin = DirectAdmin::getSSO(); // https://server2.directadminserver.com:2222/CMD_LOGIN_URL?hash=df3d...

$users =  collect(DirectAdmin::users()->all());
$userLogin = DirectAdmin::getSSO($users->first()); // https://server2.directadminserver.com:2222/CMD_LOGIN_URL?hash=hW2b...
```

You can easily use an `Eloquent` model for the `DirectAdminServer` implementation. For example:

**Migration:**
```php
Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('domain');
            $table->string('username');
            $table->string('password');
            $table->string('login_key');
            $table->boolean('usesSsl');
            $table->integer('port')->default(2222);
        });
```

**Eloquent Model:**
```php
class Server extends Model implements DirectAdminServer
{
    public function getLoginKey(): string { return $this->login_key; }
    public function getUsername(): string { return $this->username;  }
    public function getPassword(): string { return $this->password;  }
    public function usesSsl(): bool { return $this->usesSsl; }
    public function getDomain(): string { return $this->domain; }
    public function getPort(): int { return $this->port; }
}
```
