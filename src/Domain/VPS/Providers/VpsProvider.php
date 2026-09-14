<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Symfony\Component\Serializer\Serializer;
use Waterfront\Domain\VPS\Interfaces\AdminClientFactoryInterface;
use Waterfront\Domain\VPS\Interfaces\ClientFactoryInterface;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineDeploymentRepositoryInterface;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineServiceInterface;
use Waterfront\Domain\VPS\Jobs\CloudstackAsyncJob;
use Waterfront\Domain\VPS\Jobs\ReinstallVirtualMachineJob;
use Waterfront\Domain\VPS\Repositories\VirtualMachineDeploymentRepository;
use Waterfront\Domain\VPS\Services\AdminClientFactory;
use Waterfront\Domain\VPS\Services\ClientFactory;
use Waterfront\Domain\VPS\Services\CloudstackService;
use Waterfront\Domain\VPS\Services\ManagerDomainService;
use Waterfront\Domain\VPS\Services\VirtualMachineService;
use Waterfront\Domain\VPS\Services\VpsService;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;
use Waterfront\Support\Providers\BaseProvider;

class VpsProvider extends BaseProvider
{
    public function register(): void
    {
        $this->app->bind(ClientFactoryInterface::class, ClientFactory::class);
        $this->app->bind(AdminClientFactoryInterface::class, AdminClientFactory::class);
        $this->app->bind(VirtualMachineServiceInterface::class, VirtualMachineService::class);
        $this->app->bind(VirtualMachineDeploymentRepositoryInterface::class, VirtualMachineDeploymentRepository::class);

        $this->app
            ->when(ClientFactory::class)
            ->needs(ClientInterface::class)
            ->give(fn (): Client => new Client());
        $this->app
            ->when(AdminClientFactory::class)
            ->needs(ClientInterface::class)
            ->give(fn (): Client => new Client());

        $this->app
            ->when(ReinstallVirtualMachineJob::class)
            ->needs(Serializer::class)
            ->give(
                fn () => CloudstackSerializerFactory::get(),
            );

        $this->app
            ->when(CloudStackClient::class)
            ->needs(Serializer::class)
            ->give(
                fn () => CloudstackSerializerFactory::get(),
            );

        $this->app
            ->when(CloudstackService::class)
            ->needs(Serializer::class)
            ->give(
                fn () => CloudstackSerializerFactory::get(),
            );

        $this->app
            ->when(VpsService::class)
            ->needs(Serializer::class)
            ->give(
                fn () => CloudstackSerializerFactory::getCamelCaseSerializer(),
            );

        $this->app
            ->when(CloudstackAsyncJob::class)
            ->needs(Serializer::class)
            ->give(
                fn () => CloudstackSerializerFactory::getCamelCaseSerializer(),
            );

        $this->app
            ->when(VirtualMachineService::class)
            ->needs(Serializer::class)
            ->give(
                fn () => CloudstackSerializerFactory::getCamelCaseSerializer(),
            );

        $this->app
            ->when(ManagerDomainService::class)
            ->needs(Serializer::class)
            ->give(
                fn () => CloudstackSerializerFactory::getCamelCaseSerializer(),
            );
    }
}
