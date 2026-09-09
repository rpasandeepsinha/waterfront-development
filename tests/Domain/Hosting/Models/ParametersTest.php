<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Models;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;

#[CoversClass(Parameters::class)]
class ParametersTest extends TestCase
{
    #[Test]
    public function toArray(): void
    {
        $arr = [
            'username' => 'homer-jay',
            'password' => "d'Oh",
            'contactPersonName' => 'Homer J. Simpson',
            'emailAddress' => 'homer@springfield.org',
            'domain' => 'donut.shop',
            'ipv4Address' => '127.0.0.1',
            'phpVersion' => '4.0',
            'directAdminUserName' => 'donutShop',
            'enableDns' => false,
            'enableSsh' => false,
            'enableSsl' => false,
            'enableLoginKeys' => true,
            'notify' => 'yes',
            'package' => 'standard',
            'mail_only_hosting' => false,
        ];
        $parameter = Parameters::create($arr);
        $expected = [
            'username' => 'homer-jay',
            'password' => "d'Oh",
            'contact_person_name' => 'Homer J. Simpson',
            'email_address' => 'homer@springfield.org',
            'domain' => 'donut.shop',
            'ipv4_address' => '127.0.0.1',
            'ipv6_address' => null,
            'customer_id' => null,
            'status' => Parameters::STATUS_ACTIVE,
            'specs' => [],
            'required_fields' => [
                'contactPersonName',
                'emailAddress',
                'domain',
                'ipv4Address',
            ],
            'php_version' => '4.0',
            'company_name' => null,
            'forwarding_url' => null,
            'direct_admin_user_name' => 'donutShop',
            'enable_dns' => 'OFF',
            'enable_ssh' => 'OFF',
            'enable_ssl' => 'OFF',
            'enable_login_keys' => 'ON',
            'notify' => 'yes',
            'package' => 'standard',
            'mail_only_hosting' => false,
            'feature_set' => '',
            'server' => null,
        ];
        self::assertEquals($expected, $parameter->toArray(false));
    }

    #[Test]
    public function toArrayWithUsernameAndPassword(): void
    {
        $arr = [
            'username' => 'homer-jay',
            'password' => "d'Oh",
            'contactPersonName' => 'Homer J. Simpson',
            'emailAddress' => 'homer@springfield.org',
            'domain' => 'donut.shop',
            'ipv4Address' => '127.0.0.1',
            'phpVersion' => '4.0',
            'directAdminUserName' => 'donutShop',
            'enableDns' => false,
            'enableSsh' => false,
            'enableSsl' => false,
            'enableLoginKeys' => false,
            'notify' => 'yes',
            'package' => 'standard',
            'mail_only_hosting' => false,
        ];
        $expected = [
            'contact_person_name' => 'Homer J. Simpson',
            'email_address' => 'homer@springfield.org',
            'domain' => 'donut.shop',
            'ipv4_address' => '127.0.0.1',
            'ipv6_address' => null,
            'customer_id' => null,
            'status' => Parameters::STATUS_ACTIVE,
            'specs' => [],
            'required_fields' => [
                'contactPersonName',
                'emailAddress',
                'domain',
                'ipv4Address',
            ],
            'php_version' => '4.0',
            'company_name' => null,
            'forwarding_url' => null,
            'direct_admin_user_name' => 'donutShop',
            'enable_dns' => 'OFF',
            'enable_ssh' => 'OFF',
            'enable_ssl' => 'OFF',
            'enable_login_keys' => 'OFF',
            'notify' => 'yes',
            'package' => 'standard',
            'mail_only_hosting' => false,
            'feature_set' => '',
            'server' => null,
        ];
        $parameter = Parameters::create($arr);

        self::assertEquals($expected, $parameter->toArray(true));
    }
}
