<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\DTO;

use Waterfront\Domain\DNS\Exceptions\InvalidVanityNameserverConfigException;
use Waterfront\Infra\Configuration\ConfigurationException;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Webmozart\Assert\Assert;

readonly class DnsVanityNameserverConfigDto
{
    private function __construct(
        public string $ns1,
        public string $ns2,
        public string $ns3,
    ) {
        Assert::stringNotEmpty($ns1, 'ns1 must not be empty');
        Assert::stringNotEmpty($ns2, 'ns2 must not be empty');
        Assert::stringNotEmpty($ns3, 'ns3 must not be empty');
    }

    /**
     * @throws InvalidVanityNameserverConfigException if any value is missing/empty
     */
    public static function fromConfiguration(ConfigurationInterface $config): self
    {
        try {
            $ns1 = $config->getAsString('dns.gandi.vanity_nameservers.ns1');
            $ns2 = $config->getAsString('dns.gandi.vanity_nameservers.ns2');
            $ns3 = $config->getAsString('dns.gandi.vanity_nameservers.ns3');
        } catch (ConfigurationException $e) {
            throw new InvalidVanityNameserverConfigException('Could not read vanity nameserver config', $e);
        }

        return new self($ns1, $ns2, $ns3);
    }

    /**
     * @return string[]
     */
    public function getNameservers(): array
    {
        return [
            $this->ns1,
            $this->ns2,
            $this->ns3,
        ];
    }
}
