<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\DnsRecordValidation;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Domain\DNS\Helpers\DnsRecordValidatorHelper;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Services\DnsRecordsValidationService;
use Waterfront\Domain\DNS\Validators\DnsRecordValidator;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(DnsRecordValidator::class)]
#[CoversClass(DnsRecordsValidationService::class)]
class CnameRecordValidationTest extends IntegrationTestCase
{
    use DnsRecordValidatorHelper;

    #[Test]
    public function missingContent(): void
    {
        $data = [
            'type' => 'CNAME',
            'name' => 'google.com',
            'ttl' => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['content' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]],
            $validator->errors()->toArray(),
        );
    }

    #[Test]
    public function invalidContentType(): void
    {
        $data = [
            'type' => 'CNAME',
            'name' => 'google.com',
            'content' => 1,
            'ttl' => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['content' => [self::resolve(TranslatorInterface::class)->translate('validation.string')]],
            $validator->errors()->toArray(),
        );
    }

    #[DataProvider('providerInvalidFQDNS')]
    #[Test]
    public function invalidFQDN(mixed $value): void
    {
        $data = [
            'type' => 'CNAME',
            'name' => 'google.com',
            'content' => $value,
            'ttl' => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['content' => [self::resolve(TranslatorInterface::class)->translate('validation.fqdn')]],
            $validator->errors()->toArray(),
        );
    }

    #[DataProvider('providerValidFQDNS')]
    #[Test]
    public function validFQDN(string $value): void
    {
        $data = [
            'type' => 'CNAME',
            'name' => 'k1._domainkey.hostname.nl.',
            'content' => $value,
            'ttl' => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertFalse($validator->fails());
    }

    public static function providerValidFQDNS(): Generator
    {
        yield 'FQDN : hostname' => ['localhost'];
        yield 'FQDN " trailing dot' => ['localhost.'];
        yield 'FQDN : ends with tld' => ['domain.nl'];
        yield 'FQDN : ends with tld + ends on dot' => ['domain.nl.'];
        yield 'FQDN : with 1 subdomain' => ['first.domain.nl'];
        yield 'FQDN : with 2 subdomain' => ['first.second.domain.nl'];
        yield 'FQDN : without tld 1 subdomain' => ['first.domain.'];
        yield 'FQDN : without tld 2 subdomain' => ['first.second.domain.'];
        yield 'FQDN : without tld and dot 1 subdomain' => ['first.domain'];
        yield 'FQDN : without tld and dot 2 subdomain' => ['first.second.domain'];
        yield 'FQDN : domain with a hipen' => ['first-domain.nl'];
        yield 'FQDN : domain with digits' => ['domain1908.nl'];
        yield 'FQDN : domain starting with digits' => ['1908domain.nl'];
        yield 'FQDN : digits in labels' => ['1908.domain.20.nl'];
        yield 'FQDN : microsoft365 underscore' => ['selector1-sandwavetest-nl._domainkey.sandwavetest.onmicrosoft.com'];
    }

    public static function providerInvalidFQDNS(): Generator
    {
        yield 'FQDN : hostname with a space' => ['local host'];
        yield 'FQDN : labels with a space' => ['fir st.localhost'];
        yield 'FQDN : labels with a space2' => ['first.local host'];
        yield 'FQDN : hostname starting with a -' => ['-localhost'];
        yield 'FQDN : labels starting with a -' => ['localhost.-label'];
        yield 'FQDN : hostname starting with *' => ['*localhost'];
        yield 'FQDN : hostname with *' => ['local*host'];
        yield 'FQDN : hostname with digit tld' => ['localhost.12'];
        yield 'FQDN : exceeds the total maxlenght of 253' => [str_repeat('a', 253) . '.nl'];
    }
}
