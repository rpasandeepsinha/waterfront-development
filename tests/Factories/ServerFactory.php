<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    protected $model = Server::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ServerType::PLESK,
            'name' => $this->faker->name(),
            'hostname' => $this->faker->domainName(),
            'ipv4' => '1.2.3.4',
            'ipv6' => '::1',
            'owner' => $this->faker->company(),
            'allow_new_websites' => true,
            'secret_key' => '',
            'maximum_websites' => 1000,
        ];
    }

    public function directadmin(): ServerFactory
    {
        return $this->state(fn () => [
            'hostname' => 'single-server.nl',
            'domain' => 'single-server.nl',
            'name' => 'username123',
            'username' => 'username123',
            'password' => 'password12345',
            'loginkey' => 'loginkey12345',
            'ipv4' => '1.2.3.4',
            'ipv6' => '::1',
            'port' => 2222,
            'owner' => null,
            'allow_new_websites' => true,
            'maximum_websites' => null,
            'type' => ServerType::DIRECTADMIN,
            'use_ssl' => true,
        ]);
    }

    public function directadminMail(): ServerFactory
    {
        return $this->state(fn () => [
            'hostname' => 'single-server.nl',
            'domain' => 'single-server.nl',
            'name' => 'username123',
            'username' => 'username123',
            'password' => 'password12345',
            'loginkey' => 'loginkey12345',
            'ipv4' => '1.2.3.4',
            'ipv6' => '::1',
            'port' => 2222,
            'owner' => null,
            'allow_new_websites' => true,
            'maximum_websites' => null,
            'type' => ServerType::DIRECTADMIN_MAIL,
            'use_ssl' => true,
        ]);
    }

    public function plesk(): ServerFactory
    {
        $domain = $this->faker->domainName();

        return $this->state(fn () => [
            'hostname' => $domain,
            'domain' => $domain,
            'name' => $domain,
            'secret_key' => 'plesk_secret',
            'ipv4' => '1.2.3.4',
            'ipv6' => '::1',
            'port' => 8443,
            'owner' => null,
            'allow_new_websites' => true,
            'maximum_websites' => 5000,
            'type' => ServerType::PLESK,
            'use_ssl' => true,
        ]);
    }

    public function sitebuilder(): ServerFactory
    {
        return $this->state(fn () => [
            'port' => 8443,
            'type' => ServerType::SITEBUILDER,
            'name' => $this->faker->name(),
            'hostname' => $this->faker->domainName(),
            'ipv4' => '1.2.3.4',
            'ipv6' => '::1',
            'owner' => $this->faker->company(),
            'allow_new_websites' => true,
            'secret_key' => null,
            'username' => 'username123',
            'password' => 'password123',
            'maximum_websites' => 1000,
        ]);
    }
}
