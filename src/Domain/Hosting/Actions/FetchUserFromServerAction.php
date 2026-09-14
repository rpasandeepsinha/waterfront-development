<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Throwable;
use UnexpectedValueException;
use Waterfront\Domain\Hosting\DTO\FetchedServerUserDTO;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\MailManagement\Services\MailManagementService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Support\Enums\LoggingContextKeys;

class FetchUserFromServerAction
{
    private const array SUPPORTED_SERVER_TYPES = [
        ServerType::PLESK,
        ServerType::DIRECTADMIN,
        ServerType::DIRECTADMIN_MAIL,
    ];

    public function __construct(
        private readonly HostingService $hostingService,
        private readonly GetSsoUrlAction $getSsoUrlAction,
        private readonly MailManagementService $mailManagementService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function supports(ServerType $serverType): bool
    {
        return in_array($serverType, self::SUPPORTED_SERVER_TYPES, true);
    }

    public function execute(
        Server $server,
        string $identifier,
        string $ipAddress,
        ?string $domain,
    ): FetchedServerUserDTO {
        $providerSlug = $this->resolveProviderSlug($server);

        $errors = [];

        [$userData, $error] = $this->attempt(
            $server,
            'user config',
            fn (): array => $this->fetchUserConfig($server, $providerSlug, $identifier),
        );
        $errors = $this->append($errors, $error);

        [$ssoUrl, $error] = $this->attempt(
            $server,
            'sso url',
            fn (): string => $this->getSsoUrlAction->execute($server, $identifier, $ipAddress),
        );
        $errors = $this->append($errors, $error);

        $mailForwards = [];
        $mailUsers = [];

        if ($domain !== null) {
            [$mailForwards, $error] = $this->attempt(
                $server,
                'mail forwards',
                fn (): array => $this->fetchMailForwards($server, $providerSlug, $identifier, $domain),
            );
            $errors = $this->append($errors, $error);

            [$mailUsers, $error] = $this->attempt(
                $server,
                'mail users',
                fn (): array => $this->fetchMailUsers($server, $providerSlug, $identifier, $domain),
            );
            $errors = $this->append($errors, $error);
        }

        return new FetchedServerUserDTO(
            userData: $userData ?? [],
            ssoUrl: $ssoUrl,
            mailForwards: $mailForwards ?? [],
            mailUsers: $mailUsers ?? [],
            errors: $errors,
        );
    }

    /**
     * @template TResult
     *
     * @param callable(): TResult $lookup
     *
     * @return array{0: TResult|null, 1: array{message: string, previous: string|null, code: int|string}|null}
     */
    private function attempt(Server $server, string $lookupName, callable $lookup): array
    {
        try {
            return [$lookup(), null];

            // @phpstan-ignore-next-line
        } catch (Throwable $exception) {
            $this->logger->warning(
                'Fetching {meta} for a user failed on server {server.id}',
                [
                    LoggingContextKeys::SERVER_ID => $server->id,
                    LoggingContextKeys::META => $lookupName,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return [
                null,
                [
                    'message' => $exception->getMessage(),
                    'previous' => $exception->getPrevious()?->getMessage(),
                    'code' => $exception->getCode(),
                ],
            ];
        }
    }

    /**
     * @param array<int, array{message: string, previous: string|null, code: int|string}> $errors
     * @param array{message: string, previous: string|null, code: int|string}|null        $error
     *
     * @return array<int, array{message: string, previous: string|null, code: int|string}>
     */
    private function append(array $errors, ?array $error): array
    {
        if ($error === null) {
            return $errors;
        }

        $errors[] = $error;

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchUserConfig(Server $server, ProviderSlug $providerSlug, string $identifier): array
    {
        $userConfig = new Collection(
            $this->hostingService->getUserConfigAsAdmin($providerSlug->value, $identifier, $server),
        );

        if ($providerSlug === ProviderSlug::PLESK) {
            $userConfig = $this->stripPleskCredentials($userConfig);
        }

        return $userConfig->toArray();
    }

    /**
     * @return array<int, array{source: string, destinations: array<int, string>}>
     */
    private function fetchMailForwards(
        Server $server,
        ProviderSlug $providerSlug,
        string $identifier,
        string $domain,
    ): array {
        $emailForwards = $this->mailManagementService->getEmailForwards(
            domain: $domain,
            server: $server,
            username: $identifier,
            providerSlug: $providerSlug,
        );

        $forwards = [];

        foreach ($emailForwards as $emailForward) {
            $forwards[] = [
                'source' => sprintf('%s@%s', $emailForward->getSource(), $domain),
                'destinations' => $emailForward->getDestinations(),
            ];
        }

        return $forwards;
    }

    /**
     * @return array<int, string>
     */
    private function fetchMailUsers(
        Server $server,
        ProviderSlug $providerSlug,
        string $identifier,
        string $domain,
    ): array {
        $response = $this->mailManagementService->getEmailUsersRaw(
            providerSlug: $providerSlug,
            server: $server,
            username: $identifier,
            domain: $domain,
        );

        /** @var array<int, string> $users */
        $users = Arr::get($response, 'users', []);

        return $users;
    }

    private function resolveProviderSlug(Server $server): ProviderSlug
    {
        return match ($server->type) {
            ServerType::PLESK => ProviderSlug::PLESK,
            ServerType::DIRECTADMIN, ServerType::DIRECTADMIN_MAIL => ProviderSlug::DIRECTADMIN,
            default => throw new UnexpectedValueException(
                sprintf('Unable to resolve a driver for server with ID {%d}', $server->id),
            ),
        };
    }

    /**
     * Plesk returns the account password in its response; never hand that back.
     *
     * The element is removed outright rather than masked, and the pattern is
     * lazy and dot-all: a greedy `.+` swallows everything between the first and
     * last password element, and without `s` a password split over lines is not
     * matched at all.
     *
     * @param Collection<string, mixed> $userConfig
     *
     * @return Collection<string, mixed>
     */
    private function stripPleskCredentials(Collection $userConfig): Collection
    {
        if ($userConfig->has('response_result')) {
            $userConfig = $userConfig->map(function (mixed $item, string $key): mixed {
                if ($key === 'response_result' && is_string($item)) {
                    return preg_replace('/<password>.*?<\/password>/s', '', $item);
                }

                return $item;
            });
        }

        return $userConfig->except('response_body.customer.get.result.data.gen_info.password');
    }
}
