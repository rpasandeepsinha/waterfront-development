<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Users;

use Illuminate\Support\Arr;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class ShowAllUsers extends DirectAdminCommand
{
    protected string $command = 'CMD_API_SHOW_ALL_USERS';

    protected bool $useJsonResponse = false;

    protected bool $urlDecode = true;

    /**
     * Get the users after the API call.
     *
     * @return mixed[]
     */
    public function getUserList(): array
    {
        if (! Arr::has($this->getFormValues(), 'list')) {
            return [];
        }

        /** @var string[] $list */
        $list = $this->getFormValues()['list'];
        return $list;
    }
}
