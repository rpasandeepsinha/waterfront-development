<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use Exception;
use GuzzleHttp\Client;
use Waterfront\Domain\Domains\DTO\ModifyParameters;
use Waterfront\Domain\Domains\Interfaces\HandleInterface;
use Waterfront\Infra\OpenproviderClient\Interfaces\OpenProviderConnectionInterface;
use Waterfront\Infra\OpenproviderClient\Traits\RequestDomainTrait;

class DomainModifyRequest extends BaseRequest
{
    use RequestDomainTrait;

    private string $endpoint = 'modifyDomainRequest';

    private ModifyParameters $parameters;

    private ?HandleInterface $handles = null;

    /**
     * @throws Exception
     */
    public function __construct(Client $client, OpenProviderConnectionInterface $connection, string $domain)
    {
        parent::__construct($client, $connection);

        $this->setDomain($domain);
    }

    public function setParameters(ModifyParameters $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function setHandles(?HandleInterface $handles): void
    {
        $this->handles = $handles;
    }

    /**
     * Get the handles for the request.
     */
    public function getHandles(): ?HandleInterface
    {
        return $this->handles;
    }

    /**
     * {@inheritDoc}
     */
    protected function getMessage(): array
    {
        $message = parent::getMessage();

        // Handles
        $handles = [];
        if ($this->handles !== null) {
            $handles = array_filter(
                [
                    'ownerHandle' => $this->handles->getOwnerHandle(),
                    'adminHandle' => $this->handles->getAdminHandle(),
                    'techHandle' => $this->handles->getTechHandle(),
                    'billingHandle' => $this->handles->getBillingHandle(),
                ],
                fn (mixed $value): bool => (bool) $value,
            );
        }

        // Nameservers
        $nameServers = $this->parameters->getNameServers();
        if ($nameServers !== null && key($nameServers) !== 'array') {
            $nameServers = [
                'array' => [
                    'item' => $this->parameters->getNameServers(),
                ],
            ];
        }

        // Lock
        $isLocked = null;
        if (! is_null($this->parameters->getIsLocked())) {
            $isLocked = '1';
            if (! $this->parameters->getIsLocked()) {
                $isLocked = '0';
            }
        }

        // Payload
        $message[$this->endpoint] = array_merge(
            $handles,
            array_filter(
                [
                    'domain' => [
                        'name' => $this->domain->getName(),
                        'extension' => $this->domain->getExtension(),
                    ],
                    'nsGroup' => $this->parameters->getNameServerGroup(),
                    'nameServers' => $nameServers,
                    'isLocked' => $isLocked,
                    'autorenew' => $this->parameters->getAutoRenewAsString(),
                ],
                fn ($value): bool => ! is_null($value),
            ),
        );

        $message = $this->setPrivateWhois($message);

        return $this->setDnssec($message);
    }

    /**
     * @param mixed[][] $message
     *
     * @return mixed[][]
     */
    private function setPrivateWhois(array $message): array
    {
        $privateWhois = $this->parameters->getIsPrivateWhoisEnabled();

        if (is_bool($privateWhois)) {
            if ($privateWhois) {
                $message[$this->endpoint]['isPrivateWhoisEnabled'] = '1';
            } else {
                $message[$this->endpoint]['isPrivateWhoisEnabled'] = '0';
            }
        }

        return $message;
    }

    /**
     * @param mixed[][] $message
     *
     * @return mixed[]
     */
    private function setDnssec(array $message): array
    {
        $dnssec = $this->parameters->getDnssecKeys();

        if (is_array($dnssec)) {
            if (count($dnssec) > 0) {
                // Only first key for now, because we can't add multiple "item" items to the array
                $message[$this->endpoint]['isDnssecEnabled'] = $this->parameters->getIsDnssecEnabled();
                $message[$this->endpoint]['dnssecKeys'] = [
                    'array' => [
                        'item' => [
                            'flags' => $dnssec[0]->getFlags(),
                            'alg' => $dnssec[0]->getAlgorithm(),
                            'protocol' => $dnssec[0]->getProtocol(),
                            'pubKey' => $dnssec[0]->getPubKey(),
                        ],
                    ],
                ];
            } else {
                // Disable
                $message[$this->endpoint]['isDnssecEnabled'] = '0';
                $message[$this->endpoint]['dnssecKeys'] = [];
            }
        }

        return $message;
    }
}
