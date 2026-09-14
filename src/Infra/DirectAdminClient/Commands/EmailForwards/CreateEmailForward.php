<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\EmailForwards;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class CreateEmailForward extends DirectAdminCommand
{
    protected string $command = 'CMD_API_EMAIL_FORWARDERS';

    protected string $method = 'POST';

    protected bool $useJsonResponse = true;

    private string $domain;

    private string $user;

    private string $email;

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setDomain(string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    public function setUser(string $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @param array<int, string> $emails
     */
    public function setEmail(array $emails): self
    {
        $this->email = implode(',', $emails);

        return $this;
    }

    public function responseReceived(array $decodedContent): static
    {
        if (array_key_exists('success', $decodedContent) && $decodedContent['success'] === 'Forwarder created') {
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
            'action' => 'create',
            'domain' => $this->getDomain(),
            'user' => $this->getUser(),
            'email' => $this->getEmail(),
        ];

        return Utils::streamFor(http_build_query($params));
    }
}
