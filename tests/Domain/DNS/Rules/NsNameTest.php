<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Rules;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Domain\DNS\Helpers\DnsRecordValidatorHelper;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Rules\NsSubdomainName;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(NsSubdomainName::class)]
class NsNameTest extends IntegrationTestCase
{
    use DnsRecordValidatorHelper;

    #[DataProvider('providerValidNs')]
    #[Test]
    public function validNs(string $value): void
    {
        $data = [
            'domain' => 'domain.nl',
            'name' => $value,
            'content' => 'test',
            'ttl' => '600',
            'disabled' => true,
        ];

        $translator = self::resolve(TranslatorInterface::class);
        $rule = new NsSubdomainName($translator);
        $rule->setData($data);
        $rule->validate('name', $value, self::assertClosureIsCalled(false));
    }

    #[Test]
    public function invalidDomainShouldFail(): void
    {
        $subdomain = 'subdomain.domain.nl';
        $data = [
            'domain' => [],
            'name' => $subdomain,
            'content' => 'test',
            'ttl' => '600',
            'disabled' => true,
        ];

        $translator = self::resolve(TranslatorInterface::class);
        $rule = new NsSubdomainName($translator);
        $rule->setData($data);
        $rule->validate('name', $subdomain, self::assertClosureIsCalled(true));

        $data['domain'] = null;
        $rule->setData($data);
        $rule->validate('name', $subdomain, self::assertClosureIsCalled(true));
    }

    #[DataProvider('providerInvalidNs')]
    #[Test]
    public function invalidNs(mixed $value): void
    {
        $data = [
            'domain' => 'domain.nl',
            'type' => DnsRecordType::NS->value,
            'name' => $value,
            'content' => 'test',
            'ttl' => '600',
            'disabled' => true,
        ];

        $translator = self::resolve(TranslatorInterface::class);
        $rule = new NsSubdomainName($translator);
        $rule->setData($data);
        $rule->validate('name', $value, self::assertClosureIsCalled(true));
    }

    public static function providerValidNs(): Generator
    {
        yield 'with 1 subdomain' => ['first.domain.nl'];
        yield 'with 2 subdomains' => ['first.second.domain.nl'];

        yield 'wildcard on root-subdomain' => ['*.domain.nl'];
        yield 'wildcard with extra label' => ['*.something.domain.nl'];

        yield 'hyphen in label' => ['test-bla.domain.nl'];

        yield 'uppercase fqdn should pass' => ['First.Domain.NL'];
        yield 'trailing dot should pass' => ['first.domain.nl.'];
    }

    public static function providerInvalidNs(): Generator
    {
        yield 'hostname with a space' => ['local host'];
        yield 'labels with a space' => ['fir st.localhost'];
        yield 'subdomain label contains space' => ['sub domain.subdomain.domain.nl'];

        yield 'empty label (double dot)' => ['sub..domain.nl'];

        yield 'label starts with hyphen' => ['-bla.domain.nl'];
        yield 'label ends with hyphen' => ['bla-.domain.nl'];
        yield 'label is only hyphen' => ['-.domain.nl'];
        yield 'label starts with hyphen on non-domain suffix' => ['localhost.-label'];

        yield 'wildcard is not a full label' => ['*bla.domain.nl'];
        yield 'wildcard not left-most label' => ['bla.*.domain.nl'];
        yield 'double wildcard label' => ['*.*.domain.nl'];
        yield 'hostname starting with * (no dot + not wildcard label)' => ['*localhost'];

        yield 'root domain should fail' => ['domain.nl'];
        yield 'other domain name' => ['test.nl'];
        yield 'contains domain but not a subdomain' => ['domain.nl.evil.nl'];
        yield 'ends with similar but not matching suffix' => ['sub.domain.nlx'];
        yield 'hostname without domain suffix' => ['localhost'];
        yield 'hostname with digit tld (not under domain.nl)' => ['localhost.12'];

        yield 'exceeds the total max length of 253' => [str_repeat('a', 253) . '.nl'];
    }
}
