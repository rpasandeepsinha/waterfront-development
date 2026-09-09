<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Actions;

use Illuminate\Support\Collection;
use JsonException;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Throwable;
use UnexpectedValueException;
use Waterfront\Domain\Hosting\Actions\GetSsoUrlAction;
use Waterfront\Domain\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class NovaFetchUserFromResellerSubscription extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly HostingService $hostingService,
        private readonly GetSsoUrlAction $getSsoUrlAction,
        private readonly HostingServiceFactory $hostingServiceFactory,
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.fetch_user_from_subscription');
    }

    /**
     * @param Collection<int, ResellerHostingDeployment> $models
     *
     * @throws JsonException
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|Action
    {
        $resellerDeployment = $models->firstOrFail();
        $server = $resellerDeployment->server;
        Assert::notNull($server);

        $identifier = $resellerDeployment->relevant_username;
        $driver = $this->hostingServiceFactory->getDriverFromServer($server)->value;

        if ($identifier === null) {
            return self::danger($this->translator->translate('nova-action.error.no_identifier_provided'));
        }

        $fetchedUser = [];
        $sso         = null;
        $exceptions  = [];

        try {
            // Fetch user
            $fetchedUser = $this->hostingService->getUserConfig($driver, $identifier, $server);
            $collection = new Collection($fetchedUser);
            $fetchedUser = match ($driver) {
                ProviderSlug::PLESK->value => $this->filterPleskCredentials($collection),
                ProviderSlug::DIRECTADMIN->value => $collection,
                default => throw new UnexpectedValueException()
            };
        } catch (Throwable $exception) { // @phpstan-ignore-line
            $exceptions[] = [
                'message' => $exception->getMessage(),
                'previous' => $exception->getPrevious()?->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'code' => $exception->getCode(),
            ];
        }

        try {
            $sso = $this->getSsoUrlAction->execute($server, $identifier, '127.0.0.1');
        } catch (Throwable $exception) { // @phpstan-ignore-line
            $exceptions[] = [
                'message' => $exception->getMessage(),
                'previous' => $exception->getPrevious()?->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'code' => $exception->getCode(),
            ];
        }

        $data = [
            'userData' => $fetchedUser,
            'sso' => $sso,
            'exceptions' => $exceptions,
        ];

        $title = sprintf(
            'Fetched reseller {%s} from server with hostname {%s} with response:',
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
}
