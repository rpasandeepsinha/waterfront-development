<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Apps\API\Compass\DTO\ImportHostingServersDTO;
use Waterfront\Apps\API\Compass\Requests\FetchServerUserRequest;
use Waterfront\Apps\API\Compass\Requests\ImportHostingServersRequest;
use Waterfront\Apps\API\Compass\Requests\ShowServerPackageRequest;
use Waterfront\Apps\API\Compass\Requests\StoreHostingServerRequest;
use Waterfront\Apps\API\Compass\Requests\StoreLegacyRedirectingServerRequest;
use Waterfront\Apps\API\Compass\Requests\UpdateHostingServerRequest;
use Waterfront\Apps\API\Compass\Requests\UpdateLegacyRedirectingServerRequest;
use Waterfront\Apps\API\Compass\Resources\Infrastructure\DnsTemplateResource;
use Waterfront\Apps\API\Compass\Resources\Infrastructure\FetchedServerUserResource;
use Waterfront\Apps\API\Compass\Resources\Infrastructure\HostingServerResource;
use Waterfront\Apps\API\Compass\Resources\Infrastructure\LegacyRedirectingServerResource;
use Waterfront\Apps\API\Compass\Resources\Infrastructure\NameserverResource;
use Waterfront\Apps\API\Compass\Resources\Infrastructure\ServerPackageResource;
use Waterfront\Apps\API\Compass\Resources\Infrastructure\ServerPackagesResource;
use Waterfront\Domain\DNS\Models\DnsNameserver;
use Waterfront\Domain\DNS\Models\DnsTemplate;
use Waterfront\Domain\Hosting\Actions\FetchServerPackagesAction;
use Waterfront\Domain\Hosting\Actions\FetchUserFromServerAction;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\ProviderRepository;
use Waterfront\Domain\Servers\Actions\ImportHostingServersAction;
use Waterfront\Domain\Servers\Actions\StoreServerAction;
use Waterfront\Domain\Servers\Actions\UpdateServerAction;
use Waterfront\Domain\Servers\DTO\LegacyRedirectingServerDTO;
use Waterfront\Domain\Servers\DTO\ServerDTO;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\LegacyRedirectingServer;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Servers\Serializers\ServerSerializerFactory;
use Waterfront\Domain\Servers\Services\LegacyRedirectingServerService;
use Waterfront\Infra\Authentication\Attributes\RequirePermission;
use Waterfront\Infra\Translation\TranslatorInterface;

class InfrastructureController
{
    public function __construct(
        private readonly ProviderRepository $providerRepository,
        private readonly LegacyRedirectingServerService $legacyRedirectingServerService,
        private readonly ImportHostingServersAction $importHostingServersAction,
        private readonly TranslatorInterface $translator,
        private readonly StoreServerAction $storeServerAction,
        private readonly UpdateServerAction $updateServerAction,
        private readonly ServerSerializerFactory $serverSerializerFactory,
        private readonly FetchServerPackagesAction $fetchServerPackagesAction,
        private readonly FetchUserFromServerAction $fetchUserFromServerAction,
    ) {
    }

    public function getProviders(ProviderType $providerType): JsonResponse
    {
        return new JsonResponse($this->providerRepository->getAllByType($providerType));
    }

    public function listHostingServers(Request $request): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $servers = Server::paginate($pageSize);
        $servers->appends('pageSize', (string) $pageSize);

        return HostingServerResource::collection($servers)->additional([
            'meta' =>
                ['totalServers' => $servers->total()],
        ]);
    }

    public function listNameservers(Request $request): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $nameservers = DnsNameserver::with('dnsRegion')->paginate($pageSize);
        $nameservers->appends('pageSize', (string) $pageSize);

        return NameserverResource::collection($nameservers)->additional([
            'meta' =>
                ['totalServers' => $nameservers->total()],
        ]);
    }

    public function listDnsTemplates(Request $request): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $dnsTemplates = DnsTemplate::with('recordSets')->paginate($pageSize);
        $dnsTemplates->appends('pageSize', (string) $pageSize);

        return DnsTemplateResource::collection($dnsTemplates)->additional([
            'meta' =>
                ['totalServers' => $dnsTemplates->total()],
        ]);
    }

    public function importHostingServers(ImportHostingServersRequest $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('csv_upload');

        $importHostingServersDTO = new ImportHostingServersDTO(
            ServerType::from($request->string('server_type')->toString()),
            $file->getContent(),
        );

        $importedCount = $this->importHostingServersAction->execute(
            $importHostingServersDTO->serverType,
            $importHostingServersDTO->csvContents,
        );

        return new JsonResponse([
            'message' => $this->translator->translate(
                'nova-action.success.import_hosting_servers_successfully',
                ['imported_count' => (string) $importedCount]
            ),
            'errors' => [],
        ]);
    }

    public function fetchServerUser(FetchServerUserRequest $request, Server $server): string
    {
        return FetchedServerUserResource::make(
            $this->fetchUserFromServerAction->execute(
                server: $server,
                identifier: $request->identifier,
                ipAddress: $request->ipaddress ?? '127.0.0.1',
                domain: $request->domain,
            )
        )->toJson();
    }

    public function listServerPackages(Server $server): string
    {
        return ServerPackagesResource::make(
            $this->fetchServerPackagesAction->listPackages($server)
        )->toJson();
    }

    public function showServerPackage(ShowServerPackageRequest $request, Server $server): string
    {
        return ServerPackageResource::make(
            $this->fetchServerPackagesAction->fetchPackage($server, $request->name)
        )->toJson();
    }

    #[RequirePermission(Permissions::MANAGE_MIGRATIONS, SchemaId::EMPLOYEE)]
    public function listLegacyRedirectingServers(Request $request): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;

        $legacyRedirectingServers = LegacyRedirectingServer::query()
            ->orderBy('hostname')
            ->paginate($pageSize);
        $legacyRedirectingServers->appends('pageSize', (string) $pageSize);

        return LegacyRedirectingServerResource::collection($legacyRedirectingServers)->additional([
            'meta' => ['totalLegacyRedirectingServers' => $legacyRedirectingServers->total()],
        ]);
    }

    #[RequirePermission(Permissions::MANAGE_MIGRATIONS, SchemaId::EMPLOYEE)]
    public function showLegacyRedirectingServer(LegacyRedirectingServer $legacyRedirectingServer): string
    {
        return LegacyRedirectingServerResource::make($legacyRedirectingServer)->toJson();
    }

    #[RequirePermission(Permissions::MANAGE_MIGRATIONS, SchemaId::EMPLOYEE)]
    public function storeLegacyRedirectingServer(StoreLegacyRedirectingServerRequest $request): JsonResponse
    {
        $legacyRedirectingServer = $this->legacyRedirectingServerService->store(
            new LegacyRedirectingServerDTO(
                hostname: $request->hostname,
                ipv4: $request->ipv4,
                ipv6: $request->ipv6,
                originalBusinessUnit: $request->originalBusinessUnit,
            )
        );

        return new JsonResponse(['id' => $legacyRedirectingServer->id], Response::HTTP_CREATED);
    }

    #[RequirePermission(Permissions::MANAGE_MIGRATIONS, SchemaId::EMPLOYEE)]
    public function updateLegacyRedirectingServer(
        UpdateLegacyRedirectingServerRequest $request,
        LegacyRedirectingServer $legacyRedirectingServer,
    ): Response {
        $this->legacyRedirectingServerService->update(
            $legacyRedirectingServer,
            new LegacyRedirectingServerDTO(
                hostname: $request->hostname,
                ipv4: $request->ipv4,
                ipv6: $request->ipv6,
                originalBusinessUnit: $request->originalBusinessUnit,
            )
        );

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function showHostingServer(Server $server): string
    {
        return HostingServerResource::make($server)->toJson();
    }

    public function storeHostingServer(StoreHostingServerRequest $request): JsonResponse
    {
        $serverDTO = $this->serverSerializerFactory->get()->denormalize($request->all(), ServerDTO::class);

        $server = $this->storeServerAction->execute($serverDTO);

        return new JsonResponse(HostingServerResource::make($server)->resolve(), Response::HTTP_CREATED);
    }

    public function updateHostingServer(UpdateHostingServerRequest $request, Server $server): JsonResponse
    {
        $serverDTO = $this->serverSerializerFactory->get()->denormalize($request->all(), ServerDTO::class);

        $server = $this->updateServerAction->execute($server, $serverDTO);

        return new JsonResponse(HostingServerResource::make($server)->resolve());
    }

    public function showDnsTemplate(DnsTemplate $dnsTemplate): string
    {
        return DnsTemplateResource::make($dnsTemplate)->toJson();
    }
}
