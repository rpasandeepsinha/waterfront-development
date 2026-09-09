<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use JsonException;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Throwable;
use UnexpectedValueException;
use Waterfront\Domain\Hosting\Actions\GetSsoUrlAction;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\MailManagement\Services\MailManagementService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaFetchUserFromServer extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly HostingService $hostingService,
        private readonly GetSsoUrlAction $getSsoUrlAction,
        private readonly MailManagementService $mailOnlyService
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.fetch_user_from_server');
    }

    /**
     * @param Collection<int, Server> $models
     *
     * @throws JsonException
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        /** @var Server $server */
        $server = $models->firstOrFail();
        /** @var string|int|null $identifier */
        $identifier = $fields->get('identifier');
        /** @var string|null $username */
        $username = $fields->get('username');
        /** @var string|null $ipAddress */
        $ipAddress = $fields->get('ipaddress');
        /** @var string|null $domain */
        $domain = $fields->get('domain');

        if ($identifier === null) {
            return self::danger($this->translator->translate('nova-action.error.no_identifier_provided'));
        }
        $driver      = $this->getDriverFromSlug($server->type, $server);
        $fetchedUser = [];
        $fetchedEmailForwards = [];
        $fetchedEmailUsers    = [];
        $sso         = null;
        $exceptions  = [];

        try {
            $fetchedUser = $this->hostingService->getUserConfigAsAdmin($driver, (string) $identifier, $server);
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
            $sso = $this->getSsoUrlAction->execute($server, $username, $ipAddress ?? '127.0.0.1');
        } catch (Throwable $exception) { // @phpstan-ignore-line
            $exceptions[] = [
                'message' => $exception->getMessage(),
                'previous' => $exception->getPrevious()?->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'code' => $exception->getCode(),
            ];
        }

        try {
            if ($domain !== null && $username !== null) {
                $emailForwards = $this->mailOnlyService->getEmailForwards(
                    domain: $domain,
                    server: $server,
                    username: (string) $identifier,
                    providerSlug: ProviderSlug::from($driver),
                );

                foreach ($emailForwards as $emailForward) {
                    $fetchedEmailForwards[] = $emailForward->toArray($domain);
                }
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
            if ($domain !== null && $username !== null) {
                $fetchedEmailUsers = $this->mailOnlyService->getEmailUsersRaw(
                    providerSlug: ProviderSlug::from($driver),
                    server: $server,
                    username: $username,
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
            'mailForwards' => $fetchedEmailForwards,
            'mailUsers' => $fetchedEmailUsers,
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
     * @return array<int, Text>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('identifier', 'identifier'),
            Text::make('username', 'username'),
            Text::make('ipaddress', 'ipaddress'),
            Text::make('domain', 'domain'),
        ];
    }

    private function getDriverFromSlug(ServerType $slug, Server $server): string
    {
        return match($slug) {
            ServerType::PLESK => ProviderSlug::PLESK->value,
            ServerType::DIRECTADMIN, ServerType::DIRECTADMIN_MAIL => ProviderSlug::DIRECTADMIN->value,
            default => throw new UnexpectedValueException(
                sprintf(
                    'Unable to resolve driver from the selected server with ID: {%d}',
                    $server->id
                )
            )
        };
    }

    /**
     * @param Collection<string, mixed> $collection
     *
     * @return Collection<string, mixed>
     */
    private function filterPleskCredentials(Collection $collection): Collection
    {
        if ($collection->has('response_result')) {
            $collection = $collection->map(function ($item, $key) {
                if ($key === 'response_result' && is_string($item)) {
                    $pattern = '/<password>.+<\/password>/';
                    $replacement = '<password>***********</password>';
                    return preg_replace($pattern, $replacement, $item);
                }

                return $item;
            });
        }
        return $collection->except('response_body.customer.get.result.data.gen_info.password');
    }
}
