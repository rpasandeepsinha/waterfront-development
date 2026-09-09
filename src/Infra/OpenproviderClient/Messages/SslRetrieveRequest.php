<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

class SslRetrieveRequest extends BaseRequest
{
    private string $endpoint = 'retrieveOrderSslCertRequest';

    private int $certificateId;

    public function setCertificateId(int $certificateId): void
    {
        $this->certificateId = $certificateId;
    }

    /**
     * {@inheritDoc}
     */
    protected function getMessage(): array
    {
        $message = parent::getMessage();

        $message[$this->endpoint] = [
            'id' => $this->certificateId,
        ];

        return $message;
    }
}
