<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Waterfront\Domain\Ferry\Models\FerryInternalNameserver;

class InternalNamserverRepository
{
    public function isInternalNamserver(string $nameserver): bool
    {
        return FerryInternalNameserver::query()
            ->where('nameserver_hostname', $nameserver)
            ->exists();
    }

    /**
     * @return LengthAwarePaginator<int, FerryInternalNameserver>
     */
    public function paginate(int $pageSize): LengthAwarePaginator
    {
        return FerryInternalNameserver::query()
            ->orderBy('id')
            ->paginate($pageSize);
    }

    public function store(string $nameserverHostname): FerryInternalNameserver
    {
        $nameserver = new FerryInternalNameserver();
        $nameserver->nameserver_hostname = $nameserverHostname;
        $nameserver->save();

        return $nameserver;
    }

    public function updateHostname(FerryInternalNameserver $nameserver, string $nameserverHostname): void
    {
        $nameserver->nameserver_hostname = $nameserverHostname;
        $nameserver->save();
    }

    public function delete(FerryInternalNameserver $nameserver): void
    {
        $nameserver->delete();
    }
}
