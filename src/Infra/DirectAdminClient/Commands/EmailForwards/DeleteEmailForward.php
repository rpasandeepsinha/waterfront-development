<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\EmailForwards;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class DeleteEmailForward extends DirectAdminCommand
{
    protected string $command = 'CMD_API_EMAIL_FORWARDERS';

    protected string $method = 'POST';

    protected bool $useJsonResponse = true;

    private string $domain;

    private string $select0;

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getSelect0(): string
    {
        return $this->select0;
    }

    public function setDomain(string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    public function setSelect0(string $select0): self
    {
        $this->select0 = $select0;

        return $this;
    }

    public function responseReceived(array $decodedContent): static
    {
        if (array_key_exists('success', $decodedContent) && $decodedContent['success'] === 'Forwarders deleted') {
            $this->result = $decodedContent['success'];
            $this->succeeded = true;
        }

        return $this;
    }

    protected function createRequest(): Request
    {
        return parent::createRequest()->withBody($this->getPostBody());
    }

    private function getPostBody(): StreamInterface
    {
        $params = [
            'action' => 'delete',
            'domain' => $this->getDomain(),
            'select0' => $this->getSelect0(),
        ];

        return Utils::streamFor(http_build_query($params));
    }
}
