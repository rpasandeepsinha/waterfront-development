<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Resellers;

use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class ShowResellerIPs extends DirectAdminCommand
{
    /** @var mixed[] */
    public array $ips;

    /**
     * @var string Command from DirectAdminApi
     */
    protected string $command = 'CMD_API_SHOW_RESELLER_IPS';

    /**
     * @var string Method to use to call DirectAdminApi
     */
    protected string $method = 'GET';

    /**
     * @var bool set the response to be json
     */
    protected bool $useJsonResponse = true;

    public function responseReceived(array $decodedContent): static
    {
        $this->ips = $decodedContent;
        $this->succeeded = true;

        return $this;
    }
}
