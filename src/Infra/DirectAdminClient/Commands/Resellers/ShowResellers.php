<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Resellers;

use Illuminate\Support\Arr;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class ShowResellers extends DirectAdminCommand
{
    protected string $command = 'CMD_API_SHOW_RESELLERS';

    protected bool $useJsonResponse = false;

    protected bool $urlDecode = true;

    /**
     * @return string[]
     */
    public function getResellerList(): array
    {
        if (! Arr::has($this->getFormValues(), 'list')) {
            return [];
        }

        /** @var string[] $list */
        $list = $this->getFormValues()['list'];

        return $list;
    }
}
