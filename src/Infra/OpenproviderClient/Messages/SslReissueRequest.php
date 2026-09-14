<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use Waterfront\Domain\Domains\Interfaces\HandleInterface;
use Waterfront\Domain\Ssl\Interfaces\Models\Parameters;

class SslReissueRequest extends BaseRequest
{
    private string $endpoint = 'reissueSslCertRequest';

    private int $certificateId;

    private Parameters $parameters;

    private HandleInterface $handles;

    public function setCertificateId(int $certificateId): void
    {
        $this->certificateId = $certificateId;
    }

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
            'id' => $this->certificateId,
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
