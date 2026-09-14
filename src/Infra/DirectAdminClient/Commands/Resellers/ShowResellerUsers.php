<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Resellers;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Arr;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class ShowResellerUsers extends DirectAdminCommand
{
    protected string $command = 'CMD_API_SHOW_USERS';

    protected string $method = 'POST';

    protected bool $useJsonResponse = false;

    protected bool $urlDecode = true;

    private string $reseller = '';

    /**
     * @return string[]
     */
    public function getResellerUsersList(): array
    {
        if (! Arr::has($this->getFormValues(), 'list')) {
            return [];
        }

        /** @var string[] $list */
        $list = $this->getFormValues()['list'];

        return $list;
    }

    public function getReseller(): string
    {
        return $this->reseller;
    }

    public function setReseller(string $reseller): void
    {
        $this->reseller = $reseller;
    }

    protected function createRequest(): Request
    {
        return parent::createRequest()->withBody($this->getPostBody());
    }

    /**
     * Get the POST data as StreamInterface for the Request body.
     */
    private function getPostBody(): StreamInterface
    {
        $params = [
            'reseller' => $this->getReseller(),
        ];

        return Utils::streamFor(http_build_query($params));
    }
}
