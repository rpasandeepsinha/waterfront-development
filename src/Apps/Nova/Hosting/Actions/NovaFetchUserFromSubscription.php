<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use JsonException;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Throwable;
use UnexpectedValueException;
use Waterfront\Domain\Hosting\Actions\GetSsoUrlAction;
use Waterfront\Domain\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Services\HostingDeploymentService;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\MailManagement\Factories\MailOnlyServiceFactory;
use Waterfront\Domain\MailManagement\Services\MailManagementService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\Services\BasekitFactoryInterface;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaFetchUserFromSubscription extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly HostingService $hostingService,
        private readonly GetSsoUrlAction $getSsoUrlAction,
        private readonly HostingServiceFactory $hostingServiceFactory,
        private readonly MailOnlyServiceFactory $mailOnlyServiceFactory,
        private readonly MailManagementService $mailOnlyService,
        private readonly BasekitFactoryInterface $basekitFactory,
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.fetch_user_from_subscription');
    }

    /**
     * @param Collection<int, HostingDeployment> $models
     *
     * @throws JsonException
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $hostingDeployment = $models->firstOrFail();
        $relevantProviderSlug = $this->getRelevantProviderSlug($hostingDeployment);

        /** @var HostingDeploymentService $hostingDeploymentService */
        $hostingDeploymentService = resolve(HostingDeploymentService::class);

        if ($hostingDeployment->mailProvider()->exists()) {
            $identifier = $hostingDeploymentService->getMailUsername($hostingDeployment);
            /** @var Server $server */
            $server = $hostingDeployment->mailOnlyServer;
            $driver = $this->mailOnlyServiceFactory->getDriverFromMailServer($server);
        } else {
            $identifier = $hostingDeploymentService->getUsername($hostingDeployment);
            /** @var Server $server */
            $server = $hostingDeployment->server;
            $driver = $this->hostingServiceFactory->getDriverFromServer($server)->value;
        }

        $username = $identifier;

        if ($identifier === null) {
            return self::danger($this->translator->translate('nova-action.error.no_identifier_provided'));
        }

        $fetchedUser = [];
        $fetchedEmailForwards = [];
        $fetchedEmailUsers = [];
        $sso         = null;
        $exceptions  = [];

        try {
            // Fetch user
            if ($hostingDeployment->sitebuilderProvider !== null && $hostingDeployment->basekitServer !== null) {
                $baseKitClient = $this->basekitFactory->make($hostingDeployment->basekitServer);

                if ($hostingDeployment->basekit_user_ref === null) {
                    throw new UnexpectedValueException('BaseKit user reference is null');
                }

                $fetchedUser['sitebuilder']['user'] = $baseKitClient->userApi->get($hostingDeployment->basekit_user_ref)->toArray();

                if ($hostingDeployment->basekit_site_ref === null) {
                    throw new UnexpectedValueException('BaseKit site reference is null');
                }

                $fetchedUser['sitebuilder']['site'] = $baseKitClient->sitesApi->get($hostingDeployment->basekit_site_ref)->toArray();
            } else {
                $fetchedUser = $this->hostingService->getUserConfig($driver, $identifier, $server);
                $collection = new Collection($fetchedUser);
                $fetchedUser = match ($driver) {
                    ProviderSlug::PLESK->value => $this->filterPleskCredentials($collection),
                    ProviderSlug::DIRECTADMIN->value => $collection,
                    default => throw new UnexpectedValueException()
                };
            }
        } catch (Throwable $exception) { // @phpstan-ignore-line
            $exceptions[] = [
                'message' => $exception->getMessage(),
                'previous' => $exception->getPrevious()?->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'code' => $exception->getCode(),
            ];
        }

        try {
            $sso = $this->getSsoUrlAction->execute($server, $username, '127.0.0.1');
        } catch (Throwable $exception) { // @phpstan-ignore-line
            $exceptions[] = [
                'message' => $exception->getMessage(),
                'previous' => $exception->getPrevious()?->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'code' => $exception->getCode(),
            ];
        }

        try {
            $domain = $hostingDeployment->subscription->domain;

            if ($domain !== null) {
                $emailForwards = $this->mailOnlyService->getEmailForwards(
                    domain: $domain,
                    server: $server,
                    username: $identifier,
                    providerSlug: ProviderSlug::from($relevantProviderSlug),
                );

                foreach ($emailForwards as $emailForward) {
                    $fetchedEmailForwards[] = $emailForward->toArray($domain);
                }
            }
        } catch (Throwable $exception) { // @phpstan-ignore-line
            $exceptions[] = [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'code' => $exception->getCode(),
            ];
        }

        try {
            if ($domain !== null) {
                $fetchedEmailUsers = $this->mailOnlyService->getEmailUsersRaw(
                    providerSlug: ProviderSlug::from($relevantProviderSlug),
                    server: $server,
                    username: $identifier,
                    domain: $domain
                );
                /** @var array<int, string> $fetchedEmailUsers */
                $fetchedEmailUsers = Arr::get($fetchedEmailUsers, 'users', []);
            }
        } catch (Throwable $exception) { // @phpstan-ignore-line
            $exceptions[] = [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'code' => $exception->getCode(),
            ];
        }

        $data = [
            'userData' => $fetchedUser,
            'sso' => $sso,
            'mailUsers' => $fetchedEmailUsers,
            'mailForwards' => $fetchedEmailForwards,
            'exceptions' => $exceptions,
        ];

        $title = sprintf(
            'Fetched user {%s} from server with hostname {%s} with response:',
            $identifier,
            $server->hostname
        );

        return self::modal('modal-response', [
            'title' => $title,
            'code' => json_encode($data, JSON_PRETTY_PRINT),
            'size' => '7xl',
        ]);
    }

    /**
     * @param Collection<string, mixed> $collection
     *
     * @return Collection<string, mixed>
     */
    private function filterPleskCredentials(Collection $collection): Collection
    {
        return $collection->except('response_body.customer.get.result.data.gen_info.password');
    }

    private function getRelevantProviderSlug(HostingDeployment $hostingDeployment): string
    {
        $mailProvider = $hostingDeployment->mailProvider()->first();
        $standardProvider = $hostingDeployment->provider;

        return $mailProvider instanceof Provider ?
            $mailProvider->slug->value :
            (
                $standardProvider instanceof Provider ?
                $standardProvider->slug->value :
                throw new UnexpectedValueException("Hosting deployment with ID {$hostingDeployment->id} does not have a known provider coupled. Check database.}")
            );
    }
}
