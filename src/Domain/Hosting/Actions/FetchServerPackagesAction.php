<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Actions;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Hosting\DTO\ServerPackageDTO;
use Waterfront\Domain\Hosting\DTO\ServerPackagesDTO;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Provision\Hosting\Exceptions\DriverNotDefinedException;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Support\Enums\LoggingContextKeys;

class FetchServerPackagesAction
{
    public function __construct(
        private readonly HostingService $hostingService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function listPackages(Server $server): ServerPackagesDTO
    {
        try {
            $response = $this->hostingService->getPackagesOnServer($server);
        } catch (DriverNotDefinedException|GuzzleException $exception) {
            $this->logger->warning(
                'Fetching packages failed on server {server.id}',
                [
                    LoggingContextKeys::SERVER_ID => $server->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );

            return new ServerPackagesDTO(
                packages: [],
                errors: [['message' => $exception->getMessage(), 'code' => $exception->getCode()]],
            );
        }

        return new ServerPackagesDTO(
            packages: $this->packageNames($response, $server),
            errors: [],
        );
    }

    public function fetchPackage(Server $server, string $packageName): ServerPackageDTO
    {
        try {
            /** @var array<string, mixed> $details */
            $details = $this->hostingService->getPackageOnServer($server, $packageName);
        } catch (DriverNotDefinedException|GuzzleException $exception) {
            $this->logger->warning(
                'Fetching package {meta} failed on server {server.id}',
                [
                    LoggingContextKeys::SERVER_ID => $server->id,
                    LoggingContextKeys::META => $packageName,
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );

            return new ServerPackageDTO(
                details: [],
                errors: [['message' => $exception->getMessage(), 'code' => $exception->getCode()]],
            );
        }

        return new ServerPackageDTO(details: $details, errors: []);
    }

    /**
     * @param array<mixed> $response
     *
     * @return array<int, string>
     */
    private function packageNames(array $response, Server $server): array
    {
        if (array_key_exists('list', $response) && is_array($response['list'])) {
            return $this->stringValues($response['list']);
        }

        if (array_is_list($response)) {
            return $this->stringValues($response);
        }

        $this->logger->warning(
            'Unrecognised package list shape returned by server {server.id}',
            [
                LoggingContextKeys::SERVER_ID => $server->id,
                LoggingContextKeys::META => array_slice(array_keys($response), 0, 10),
            ]
        );

        return [];
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<int, string>
     */
    private function stringValues(array $values): array
    {
        return array_values(array_filter($values, static fn (mixed $value): bool => is_string($value)));
    }
}
