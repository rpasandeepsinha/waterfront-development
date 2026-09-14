<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Mock;

use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class MockCommand extends DirectAdminCommand
{
    protected string $command = 'CMD_API_FAIL';

    protected bool $useJsonResponse = false;

    protected bool $urlDecode = true;

    public function isUseJsonResponse(): bool
    {
        return $this->useJsonResponse;
    }

    public function setUseJsonResponse(bool $useJsonResponse): void
    {
        $this->useJsonResponse = $useJsonResponse;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    public function setFailureString(string $failureString): void
    {
        $this->failureString = $failureString;
    }

    public function setCommand(string $command): void
    {
        $this->command = $command;
    }

    public function isUrlDecode(): bool
    {
        return $this->urlDecode;
    }

    public function setUrlDecode(bool $urlDecode): MockCommand
    {
        $this->urlDecode = $urlDecode;

        return $this;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }

    public function setResponseBody(string $responseBody): MockCommand
    {
        $this->responseBody = $responseBody;

        return $this;
    }
}
