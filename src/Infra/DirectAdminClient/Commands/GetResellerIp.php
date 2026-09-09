<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands;

use Illuminate\Support\Arr;

class GetResellerIp extends DirectAdminCommand
{
    protected string $command = 'CMD_API_SHOW_RESELLER_IPS';

    protected bool $useJsonResponse = false;

    protected bool $urlDecode = true;

    /**
     * @var mixed[] with ips for the DirectAdminServer
     */
    private array $ips;

    /**
     * Set the received IP's from the API.
     *
     * @param mixed[] $decodedContent
     */
    public function responseReceived(array $decodedContent): static
    {
        $this->ips = $decodedContent;
        return $this;
    }

    /**
     * @return mixed[]
     */
    public function getIps(): array
    {
        if (! Arr::has($this->ips, 'list.0')) {
            return [];
        }

        /** @var mixed[] $list */
        $list = $this->ips['list'];

        return $list;
    }
}
