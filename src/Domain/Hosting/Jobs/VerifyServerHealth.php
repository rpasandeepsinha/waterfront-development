<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Jobs;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Collection;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Provision\Hosting\Exceptions\DriverNotDefinedException;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Exceptions\ServerNotFoundException;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Exceptions\NotImplementedException;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class VerifyServerHealth extends AbstractQueueableJob
{
    public function __construct(
        private readonly ServerType $serverType,
    ) {
        parent::__construct();
    }

    public function handle(HostingService $hostingService, LoggerInterface $logger): void
    {
        /**
         * @var Collection<int, Server> $servers
         */
        $servers = Server::query()->where('type', $this->serverType)->get();

        $states = [];
        $logger->debug("fetching healthcheck for {$servers->count()} servers...");

        foreach ($servers as $key => $server) {
            try {
                $hostingService->getPackagesOnServer($server);
                $states[$key . ' ' . $server->getDomain()] = 'healthy';
            } catch (
                ServerNotFoundException|DriverNotDefinedException|DirectAdminException|GuzzleException|NotImplementedException $exception
            ) {
                $states[$key . ' ' . $server->getDomain()] = $exception::class . ': ' . $exception->getMessage();
            }
        }

        $logger->debug(
            'Debugged access ability of all hosting servers of the given type.',
            [
                LoggingContextKeys::SERVER_TYPE => $this->serverType->value,
                LoggingContextKeys::META => [
                    'hosting_server_state' => $states,
                ],
            ],
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::FERRY;
    }
}
