<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use Exception;
use GuzzleHttp\Client;
use Waterfront\Domain\Domains\DTO\TransferParameters;
use Waterfront\Domain\Domains\Interfaces\HandleInterface;
use Waterfront\Infra\OpenproviderClient\Interfaces\OpenProviderConnectionInterface;
use Waterfront\Infra\OpenproviderClient\Traits\RequestDomainTrait;

class DomainTransferRequest extends BaseRequest
{
    use RequestDomainTrait;

    private string $endpoint = 'transferDomainRequest';

    private TransferParameters $parameters;

    private HandleInterface $handles;

    /**
     * @throws Exception
     */
    public function __construct(Client $client, OpenProviderConnectionInterface $connection, string $domain)
    {
        parent::__construct($client, $connection);

        $this->setDomain($domain);
    }

    public function setParameters(TransferParameters $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function setHandles(HandleInterface $handles): void
    {
        $this->handles = $handles;
    }

    /**
     * {@inheritDoc}
     */
    protected function getMessage(): array
    {
        $message = parent::getMessage();

        // Name, handles etc.
        $message[$this->endpoint] = [
            'ownerHandle' => $this->handles->getOwnerHandle(),
            'adminHandle' => $this->handles->getAdminHandle(),
            'techHandle' => $this->handles->getTechHandle(),
            'billingHandle' => $this->handles->getBillingHandle(),
            'domain' => [
                'name' => $this->domain->getName(),
                'extension' => $this->domain->getExtension(),
            ],
            'period' => $this->parameters->getPeriod(),
            'authCode' => $this->parameters->getTransferSecret(),
            'autorenew' => 'on',
        ];

        // Nameservers
        if ($this->parameters->getNameServers() !== null) {
            $message[$this->endpoint]['nameServers'] = $this->parameters->getNameServers()->toArray();
        } else {
            $message[$this->endpoint]['nsGroup'] = $this->parameters->getNameServerGroup();
        }

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
     * @return mixed[][]
     */
    private function setDnssec(array $message): array
    {
        $dnssec = $this->parameters->getDnssecKeys();

        if ($dnssec !== null) {
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
                $message[$this->endpoint]['isDnssecEnabled'] = false;
                $message[$this->endpoint]['dnssecKeys'] = [];
            }
        }

        return $message;
    }
}
