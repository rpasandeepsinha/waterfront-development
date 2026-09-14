<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Services;

use Waterfront\Domain\DNS\Rules\CaaContent;
use Waterfront\Domain\DNS\Rules\DnsTemplateFqdn;
use Waterfront\Domain\DNS\Rules\NoDuplicate;
use Waterfront\Domain\DNS\Rules\TlsaContent;

class DnsRecordsValidationService
{
    public function __construct(
        private readonly TlsaContent $tlsaContentRule,
        private readonly CaaContent $caaContentRule,
        private readonly DnsTemplateFqdn $dnsTemplateFqdnRule,
    ) {
    }

    /**
     * @param mixed[] $customAttributes
     *
     * @return mixed[]
     */
    public function getRecordRules(string $type, array $customAttributes): array
    {
        return match ($type) {
            'A' => $this->getARecordRules($customAttributes) + $this->getDefaultRules(),
            'AAAA' => $this->getAaaaRecordRules($customAttributes) + $this->getDefaultRules(),
            'ALIAS' => $this->getAliasRecordRules($customAttributes) + $this->getDefaultRules(),
            'CAA' => $this->getCaaRecordRules() + $this->getDefaultRules(),
            'CNAME' => $this->getCnameRecordRules($customAttributes) + $this->getDefaultRules(),
            'MX' => $this->getMxRecordRules() + $this->getDefaultRules(),
            'SPF' => $this->getSpfRecordRules() + $this->getDefaultRules(),
            'SRV' => $this->getSrvRecordRules() + $this->getDefaultRules(),
            'TLSA' => $this->getTlsaRecordRules() + $this->getDefaultRules(),
            'TXT' => $this->getTxtRecordRules($customAttributes) + $this->getDefaultRules(),
            default => $this->getDefaultRules(),
        };
    }

    /**
     * @return mixed[]
     */
    public function getDefaultRules(): array
    {
        return [
            'type' => ['bail', 'required', 'string', 'regex:/^[A-Z]+$/'],
            'name' => ['bail', 'required', 'string', 'max:255', $this->dnsTemplateFqdnRule],
            'content' => ['bail', 'required', 'string'],
            'ttl' => ['bail', 'required', 'int', 'between:0,2147483647'],
            'disabled' => ['bail', 'nullable', 'bool'],
        ];
    }

    /**
     * @param array<mixed> $customAttributes
     *
     * @return array<mixed>
     */
    private function getARecordRules(array $customAttributes): array
    {
        $nameValidation = [
            'bail',
            'required',
            'string',
            $this->dnsTemplateFqdnRule,
        ];

        if (array_key_exists('domain', $customAttributes)) {
            $nameValidation[] = new NoDuplicate($customAttributes['domain'], ['CNAME']);
        }

        return [
            'name' => $nameValidation,
            'content' => ['bail', 'required', 'string', 'ipv4'],
        ];
    }

    /**
     * @param array<mixed> $customAttributes
     *
     * @return array<mixed>
     */
    private function getAaaaRecordRules(array $customAttributes): array
    {
        $nameValidation = [
            'bail',
            'required',
            'string',
            $this->dnsTemplateFqdnRule,
        ];

        if (array_key_exists('domain', $customAttributes)) {
            $nameValidation[] = new NoDuplicate($customAttributes['domain'], ['CNAME']);
        }

        return [
            'name' => $nameValidation,
            'content' => ['bail', 'required', 'string', 'ipv6'],
        ];
    }

    /**
     * @return array<mixed>
     */
    private function getCaaRecordRules(): array
    {
        return [
            'content' => ['bail', 'required', 'string', $this->caaContentRule],
        ];
    }

    /**
     * @param array<mixed> $customAttributes
     *
     * @return array<mixed>
     */
    private function getCnameRecordRules(array $customAttributes): array
    {
        $nameValidation = [
            'bail',
            'required',
            'string',
            $this->dnsTemplateFqdnRule,
        ];

        if (array_key_exists('domain', $customAttributes)) {
            $nameValidation[] = new NoDuplicate($customAttributes['domain'], ['A', 'AAAA', 'TXT']);
        }

        return [
            'name' => $nameValidation,
            'content' => ['bail', 'required', 'string', $this->dnsTemplateFqdnRule],
        ];
    }

    /**
     * @param array<mixed> $customAttributes
     *
     * @return array<mixed>
     */
    private function getAliasRecordRules(array $customAttributes): array
    {
        $nameValidation = [
            'bail',
            'required',
            'string',
            $this->dnsTemplateFqdnRule,
        ];

        if (array_key_exists('domain', $customAttributes)) {
            $nameValidation[] = new NoDuplicate($customAttributes['domain'], ['A', 'AAAA', 'CNAME']);
        }

        return [
            'name' => $nameValidation,
            'content' => ['bail', 'required', 'string', $this->dnsTemplateFqdnRule],
        ];
    }

    /**
     * @return array<mixed>
     */
    private function getMxRecordRules(): array
    {
        return [
            'content' => ['bail', 'required', 'string', $this->dnsTemplateFqdnRule],
            'priority' => ['bail', 'required', 'int', 'between:0,65535'],
        ];
    }

    /**
     * @return array<mixed>
     */
    private function getSpfRecordRules(): array
    {
        return [
            'content' => ['bail', 'required', 'string'],
        ];
    }

    /**
     * @return array<mixed>
     */
    private function getSrvRecordRules(): array
    {
        return [
            'content' => ['bail', 'required', 'string', $this->dnsTemplateFqdnRule],
            'priority' => ['bail', 'required', 'int', 'between:0,65535'],
            'weight' => ['bail', 'required', 'int', 'between:0,65535'],
            'port' => ['bail', 'required', 'int', 'between:0,65535'],
        ];
    }

    /**
     * @return array<mixed>
     */
    private function getTlsaRecordRules(): array
    {
        return [
            'content' => ['bail', 'required', 'string', $this->tlsaContentRule],
        ];
    }

    /**
     * @param array<mixed> $customAttributes
     *
     * @return array<mixed>
     */
    private function getTxtRecordRules(array $customAttributes): array
    {
        $nameValidation = [
            'bail',
            'required',
            'string',
            $this->dnsTemplateFqdnRule,
        ];

        if (array_key_exists('domain', $customAttributes)) {
            $nameValidation[] = new NoDuplicate($customAttributes['domain'], ['CNAME']);
        }

        return [
            'name' => $nameValidation,
            'content' => ['bail', 'required', 'string', 'max:1000'],
        ];
    }
}
