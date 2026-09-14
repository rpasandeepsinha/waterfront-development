<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use Waterfront\Domain\Domains\Interfaces\HandleInterface;
use Waterfront\Domain\Ssl\Interfaces\Models\Parameters;

class SslCreateRequest extends BaseRequest
{
    private string $endpoint = 'createSslCertRequest';

    private Parameters $parameters;

    private HandleInterface $handles;

    public function setParameters(Parameters $parameters): void
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

        $message[$this->endpoint] = [
            'productId' => $this->parameters->getProductId(),
            'period' => $this->parameters->getPeriod(),
            'csr' => $this->parameters->getCsr(),
            'softwareId' => $this->parameters->getSoftwareId(),
            'organizationHandle' => $this->handles->getOwnerHandle(),
            'technicalHandle' => $this->handles->getTechHandle(),
            'approverEmail' => $this->parameters->getApproverEmail(),
            'domainValidationMethods' => ['array' => ['item' => $this->parameters->getDomainValidationMethods()]],
        ];

        return $message;
    }
}
