<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use Waterfront\Domain\Ssl\Interfaces\Models\Approver\Parameters;

class SslApproverRequest extends BaseRequest
{
    private string $endpoint = 'retrieveApproverEmailListSslCertRequest';

    private Parameters $parameters;

    public function setParameters(Parameters $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * @inheritDoc
     */
    protected function getMessage(): array
    {
        $message = parent::getMessage();

        $message[$this->endpoint] = [
            'domain'    => $this->parameters->getDomain(),
            'productId' => $this->parameters->getProductId(),
        ];

        return $message;
    }
}
