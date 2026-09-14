<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Users;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

/**
 * See: https://www.directadmin.com/api.php#suspend and https://www.directadmin.com/features.php?id=807.
 */
class SuspendUser extends DirectAdminCommand
{
    protected string $command = 'CMD_API_SELECT_USERS';

    protected string $method = 'POST';

    public function __construct(
        private readonly string $user,
    ) {
    }

    protected function createRequest(): Request
    {
        return parent::createRequest()->withBody($this->getPostBody());
    }

    private function getPostBody(): StreamInterface
    {
        $params = [
            //            'json' => 'yes',
            'dosuspend' => 'suspend',
            'select0' => $this->user,
        ];

        return Utils::streamFor(http_build_query($params));
    }
}
