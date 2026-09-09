<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;

class ServerRepository
{
    /**
     * @return Collection<int, Server>
     */
    public function getAllHostingServers(): Collection
    {
        return Server::all();
    }

    public function findAvailableServer(ServerType $serverType): Server
    {
        /** @var Server $server */
        $server = Server::query()
            ->where('allow_new_websites', true)
            ->where('type', $serverType)
            ->whereNull('hosting_servers.deleted_at')
            ->whereNull('hosting_deployments.deleted_at')
            ->leftJoin('hosting_deployments', 'hosting_deployments.server_id', '=', 'hosting_servers.id')
            ->select('hosting_servers.id', DB::raw('COUNT(hosting_deployments.id) as subscriptions_count'))
            ->groupBy('hosting_servers.id')
            ->orderBy('subscriptions_count')
            ->firstOrFail();

        $server->refresh();

        return $server;
    }

    /**
     * @throws ModelNotFoundException
     */
    public function findByHostname(string $hostName): Server
    {
        return Server::where('hostname', $hostName)
            ->firstOrFail();
    }

    public function getById(int $id): Server
    {
        return Server::findOrFail($id);
    }
}
