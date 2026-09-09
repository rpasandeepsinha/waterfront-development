<?php

declare(strict_types=1);

namespace Waterfront\Domain\Servers\Actions;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use ValueError;
use Waterfront\Domain\Domains\Rules\DomainNameRule;
use Waterfront\Domain\Ferry\Mappers\CsvParser;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\Translation\TranslatorInterface;

/**
 * Imports hosting servers in bulk from a CSV file.
 *
 * Supports both Plesk and DirectAdmin servers; the requested server type
 * decides which columns the CSV must carry and how a row is mapped onto a
 * Server.
 */
class ImportHostingServersAction
{
    private const int MAX_VALIDATION_MESSAGES = 10;

    private const int MAX_ROWS = 1000;

    /** @var array<string, array<string, true>> */
    private array $takenValues = [];

    public function __construct(
        private readonly CsvParser $csvParser,
        private readonly TranslatorInterface $translator,
        private readonly DomainNameRule $domainNameRule,
    ) {
    }

    /**
     * @throws ValidationException
     *
     * @return int The number of servers imported.
     */
    public function execute(ServerType $serverType, string $csvContents): int
    {
        $servers = $this->parse($csvContents);

        $this->assertRowCountWithinLimit($servers);

        $this->validate($servers, $serverType);

        DB::transaction(function () use ($servers, $serverType): void {
            foreach ($servers as $serverData) {
                $this->createServer($serverData, $serverType);
            }
        });

        return count($servers);
    }

    /**
     * @param array<int, array<string, string|null>> $servers
     *
     * @throws ValidationException
     */
    private function assertRowCountWithinLimit(array $servers): void
    {
        if (count($servers) <= self::MAX_ROWS) {
            return;
        }

        throw ValidationException::withMessages([
            'csv_upload' => [
                $this->translator->translate(
                    'hosting.server-import.error.too-many-rows',
                    [
                        'found' => (string) count($servers),
                        'max' => (string) self::MAX_ROWS,
                    ]
                ),
            ],
        ]);
    }

    /**
     *
     * @throws ValidationException
     *
     * @return array<int, array<string, string|null>>
     */
    private function parse(string $csvContents): array
    {
        $this->assertColumnCountsMatch($csvContents);

        try {
            return $this->csvParser->parseCsvWithHeaders($csvContents);
        } catch (ValueError) {
            throw ValidationException::withMessages([
                'csv_upload' => [
                    $this->translator->translate('nova-action.error.number_of_columns_does_not_match'),
                ],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertColumnCountsMatch(string $csvContents): void
    {
        $lines = explode("\n", $csvContents);
        $expectedColumnCount = count(str_getcsv(array_shift($lines), escape: '\\'));

        $messages = [];

        foreach ($lines as $index => $line) {
            if ($line === '') {
                continue;
            }

            $foundColumnCount = count(str_getcsv($line, escape: '\\'));

            if ($foundColumnCount === $expectedColumnCount) {
                continue;
            }

            $messages[] = $this->translator->translate(
                'hosting.server-import.error.column-count',
                [
                    'line' => (string) $this->lineNumber($index),
                    'found' => (string) $foundColumnCount,
                    'expected' => (string) $expectedColumnCount,
                ]
            );
        }

        if ($messages !== []) {
            throw ValidationException::withMessages(['csv_upload' => $messages]);
        }
    }

    private function lineNumber(int $rowIndex): int
    {
        return $rowIndex + 2;
    }

    /**
     * @param array<int, array<string, string|null>> $servers
     *
     * @throws ValidationException
     */
    private function validate(array $servers, ServerType $serverType): void
    {
        $this->loadTakenValues();

        $validator = ValidatorFacade::make($servers, $this->validationRules($serverType));

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'csv_upload' => $this->buildValidationMessages($validator->messages()),
            ]);
        }
    }

    private function loadTakenValues(): void
    {
        $columns = ['hostname', 'ipv4', 'ipv6'];

        foreach ($columns as $column) {
            /** @var array<int, string|null> $values */
            $values = Server::query()
                ->whereNull('deleted_at')
                ->whereNotNull($column)
                ->pluck($column)
                ->all();

            $this->takenValues[$column] = array_fill_keys(
                array_filter($values, static fn (?string $value): bool => $value !== null),
                true
            );
        }
    }

    private function notAlreadyTaken(string $column): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($column): void {
            if (! is_string($value)) {
                return;
            }

            if (array_key_exists($value, $this->takenValues[$column])) {
                $fail($this->translator->translate('validation.unique'));
            }
        };
    }

    /**
     * @return array<string, array<int, Closure|DomainNameRule|string>>
     */
    private function validationRules(ServerType $serverType): array
    {
        $sharedRules = [
            '*' => ['array'],
            '*.owner' => [
                'required',
                'string',
            ],
            '*.hostname' => [
                'required',
                'distinct',
                'string',
                $this->notAlreadyTaken('hostname'),
                $this->domainNameRule,
            ],
            '*.ipv4' => [
                'required',
                'distinct',
                'ipv4',
                $this->notAlreadyTaken('ipv4'),
            ],
            '*.ipv6' => [
                'nullable',
                'distinct',
                'ipv6',
                $this->notAlreadyTaken('ipv6'),
            ],
            '*.port' => [
                'required',
                'numeric',
            ],
            '*.allow_new_websites' => [
                'required',
                'boolean',
            ],
            '*.maximum_websites' => [
                'required',
                'numeric',
            ],
            '*.use_ssl' => [
                'required',
                'boolean',
            ],
        ];

        $typeSpecificRules = match ($serverType) {
            ServerType::PLESK => [
                '*.secret_key' => [
                    'required',
                    'string',
                ],
                /* Optional: an empty php_version falls back to the
                 * hosting_servers.php_version column default. */
                '*.php_version' => [
                    'nullable',
                    'string',
                ],
            ],
            ServerType::DIRECTADMIN => [
                '*.username' => [
                    'required',
                    'string',
                ],
                '*.password' => [
                    'required',
                    'string',
                ],
                '*.login_key' => [
                    'nullable',
                    'string',
                ],
            ],
            default => throw new ValueError(
                sprintf('Server type [%s] cannot be imported.', $serverType->value)
            ),
        };

        return [...$sharedRules, ...$typeSpecificRules];
    }

    /**
     * @param array<string, string|null> $serverData
     */
    private function createServer(array $serverData, ServerType $serverType): void
    {
        $server = new Server();
        $server->owner = $serverData['owner'];
        $server->type = $serverType;
        $server->name = $serverData['hostname']; // name same as hostname
        $server->hostname = (string) $serverData['hostname'];
        $server->port = intval($serverData['port']);
        $server->ipv4 = $serverData['ipv4'];
        $server->ipv6 = $serverData['ipv6'];
        $server->allow_new_websites = (bool) $serverData['allow_new_websites'];
        $server->maximum_websites = intval($serverData['maximum_websites']);
        $server->use_ssl = (bool) $serverData['use_ssl'];

        match ($serverType) {
            ServerType::PLESK => $this->applyPleskFields($server, $serverData),
            ServerType::DIRECTADMIN => $this->applyDirectAdminFields($server, $serverData),
            default => throw new ValueError(
                sprintf('Server type [%s] cannot be imported.', $serverType->value)
            ),
        };

        $server->save();
    }

    /**
     * @param array<string, string|null> $serverData
     */
    private function applyPleskFields(Server $server, array $serverData): void
    {
        $server->secret_key = $serverData['secret_key'];

        if ($serverData['php_version'] !== null) {
            $server->php_version = $serverData['php_version'];
        }
    }

    /**
     * @param array<string, string|null> $serverData
     */
    private function applyDirectAdminFields(Server $server, array $serverData): void
    {
        $server->username = $serverData['username'];
        $server->password = $serverData['password'];
        $server->loginkey = $serverData['login_key'];
    }

    /**
     * @return string[]
     */
    private function buildValidationMessages(MessageBag $messageBag): array
    {
        $validationErrors = $messageBag->toArray();

        // Sort by row number, instead of by field name.
        uksort($validationErrors, function (string $key1, string $key2): int {
            [$rowNumber1] = explode('.', $key1);
            [$rowNumber2] = explode('.', $key2);

            return (int) $rowNumber1 <=> (int) $rowNumber2;
        });

        $messages = [];

        foreach ($validationErrors as $fieldName => $fieldMessages) {
            [$rowIndex, $column] = [...explode('.', $fieldName, 2), ''];

            foreach ($fieldMessages as $message) {
                $messages[] = $column === ''
                    ? $this->translator->translate(
                        'hosting.server-import.error.line',
                        [
                            'line' => (string) $this->lineNumber((int) $rowIndex),
                            'message' => $message,
                        ]
                    )
                    : $this->translator->translate(
                        'hosting.server-import.error.line-field',
                        [
                            'line' => (string) $this->lineNumber((int) $rowIndex),
                            'field' => $column,
                            'message' => $message,
                        ]
                    );
            }
        }

        $totalMessages = count($messages);

        if ($totalMessages <= self::MAX_VALIDATION_MESSAGES) {
            return $messages;
        }

        return [
            ...array_slice($messages, 0, self::MAX_VALIDATION_MESSAGES),
            $this->translator->translate(
                'nova-action.error.import_hosting_servers_more_errors',
                [
                    'error_count' => (string) $totalMessages,
                    'max_to_display' => (string) self::MAX_VALIDATION_MESSAGES,
                ]
            ),
        ];
    }
}
