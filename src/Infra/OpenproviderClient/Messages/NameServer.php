<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use InvalidArgumentException;

class NameServer
{
    private string $hostname;

    private ?string $ipv4 = null;

    private ?string $ipv6 = null;

    public function __construct(string $hostname, ?string $ipv4 = null, ?string $ipv6 = null)
    {
        $this->setHostname($hostname);

        if ($ipv4 !== null) {
            $this->setIpv4($ipv4);
        }

        if ($ipv6 !== null) {
            $this->setIpv6($ipv6);
        }
    }

    public function getIpv4(): ?string
    {
        return $this->ipv4;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setIpv4(string $ipv4): void
    {
        if (filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new InvalidArgumentException('The ipv4 address is not valid.');
        }

        $this->ipv4 = $ipv4;
    }

    public function getIpv6(): ?string
    {
        return $this->ipv6;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setIpv6(string $ipv6): void
    {
        if (filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            throw new InvalidArgumentException('The ipv6 address is not valid.');
        }

        $this->ipv6 = $ipv6;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return array_filter(
            [
                'name' => $this->getHostname(),
                'ip'   => $this->getIpv4(),
                'ip6'  => $this->getIpv6(),
            ]
        );
    }

    private function getHostname(): string
    {
        return $this->hostname;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function setHostname(string $hostname): void
    {
        if (filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new InvalidArgumentException('The hostname is not valid.');
        }

        $this->hostname = $hostname;
    }
}
