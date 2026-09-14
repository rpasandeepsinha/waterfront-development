<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use function Laravel\Prompts\text;

#[AsCommand(
    name: 'make:one-off-script',
    description: 'Create a new one-off script with its directory, Nova action, and test file',
)]
#[Signature('make:one-off-script {name?} {ticket?}')]
class MakeOneOffScript extends AbstractCommand
{
    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->getValidatedName();
        $ticketNumber = $this->getValidatedTicketNumber();

        $slug = Str::kebab($name);
        $actionClassName = sprintf('Nova%sAction', $name);
        $ticketUrl = sprintf('https://yh-jira.atlassian.net/browse/%s', $ticketNumber);

        $srcDir = $this->laravel->basePath(sprintf('src/Apps/OneOffScripts/%s', $name));
        $testDir = $this->laravel->basePath(sprintf('tests/Apps/OneOffScripts/%s', $name));

        if ($this->filesystem->isDirectory($srcDir) || $this->filesystem->isDirectory($testDir)) {
            $this->error(sprintf('One-off script "%s" already exists.', $name));

            return self::FAILURE;
        }

        $this->filesystem->makeDirectory($srcDir, 0755, true, true);
        $this->filesystem->makeDirectory($testDir, 0755, true, true);

        $this->filesystem->put(
            sprintf('%s/%s.php', $srcDir, $actionClassName),
            $this->generateActionStub($name, $actionClassName, $slug, $ticketUrl),
        );

        $this->filesystem->put(
            sprintf('%s/%sTest.php', $testDir, $actionClassName),
            $this->generateTestStub($name, $actionClassName),
        );

        $actionFqcn = sprintf('Waterfront\\Apps\\OneOffScripts\\%s\\%s', $name, $actionClassName);
        $this->registerActionInResource($actionFqcn, $actionClassName);

        $this->components->info(sprintf('One-off script "%s" created successfully.', $name));
        $this->components->bulletList([
            sprintf('Action:   src/Apps/OneOffScripts/%s/%s.php', $name, $actionClassName),
            sprintf('Test:     tests/Apps/OneOffScripts/%s/%sTest.php', $name, $actionClassName),
            'Registered in NovaOneOffScriptResource::actions()',
        ]);

        return self::SUCCESS;
    }

    private function getValidatedName(): string
    {
        /** @var string|null $name */
        $name = $this->argument('name');

        if ($name !== null) {
            if (preg_match('/^[A-Z][a-zA-Z0-9]+$/', $name) !== 1) {
                throw new InvalidArgumentException('The name must be in PascalCase (e.g. FixBrokenSubscriptions).');
            }

            return $name;
        }

        return text(
            label: 'What is the name of the one-off script?',
            placeholder: 'e.g. FixBrokenSubscriptions',
            required: true,
            validate: function (string $value): ?string {
                if (preg_match('/^[A-Z][a-zA-Z0-9]+$/', $value) !== 1) {
                    return 'The name must be in PascalCase (e.g. FixBrokenSubscriptions).';
                }

                return null;
            },
        );
    }

    private function getValidatedTicketNumber(): string
    {
        /** @var string|null $ticket */
        $ticket = $this->argument('ticket');

        if ($ticket !== null) {
            if (preg_match('/^[A-Z]+-\d+$/', $ticket) !== 1) {
                throw new InvalidArgumentException('The ticket number must be in the format SWD-12345.');
            }

            return $ticket;
        }

        return text(
            label: 'What is the Jira ticket number?',
            placeholder: 'e.g. SWD-12345',
            required: true,
            validate: function (string $value): ?string {
                if (preg_match('/^[A-Z]+-\d+$/', $value) !== 1) {
                    return 'The ticket number must be in the format SWD-12345.';
                }

                return null;
            },
        );
    }

    private function registerActionInResource(string $actionFqcn, string $actionClassName): void
    {
        $resourcePath = $this->laravel->basePath('src/Apps/Nova/OneOffScripts/NovaOneOffScriptResource.php');
        $contents = $this->filesystem->get($resourcePath);

        // Add use import before the class declaration
        $useStatement = sprintf("use %s;\n", $actionFqcn);
        $contents = (string) preg_replace(
            '/\n(class\s+NovaOneOffScriptResource\b)/',
            "\n" . $useStatement . '$1',
            $contents,
        );

        // Add resolveAction line before the closing ]; of the actions() return array
        $actionLine = sprintf("            \$this->resolveAction(%s::class),\n", $actionClassName);
        $contents = (string) preg_replace(
            '/(public function actions\(NovaRequest \$request\): array\s*\{\s*return \[.*?)(        \];)/s',
            '$1' . $actionLine . '$2',
            $contents,
        );

        $this->filesystem->put($resourcePath, $contents);
    }

    private function generateActionStub(string $name, string $className, string $slug, string $ticketUrl): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Waterfront\Apps\OneOffScripts\\{$name};

        use Laravel\Nova\Actions\ActionResponse;
        use Laravel\Nova\Fields\ActionFields;
        use Laravel\Nova\Fields\Boolean;
        use Laravel\Nova\Fields\Field;
        use Laravel\Nova\Http\Requests\NovaRequest;
        use Psr\Log\LoggerInterface;
        use Waterfront\Apps\Nova\OneOffScripts\NovaOneOffScriptAbstractAction;
        use Waterfront\Support\Enums\LoggingContextKeys;

        class {$className} extends NovaOneOffScriptAbstractAction
        {
            public const string SLUG = '{$slug}';

            public function __construct(
                private readonly LoggerInterface \$logger,
            ) {
                parent::__construct();
            }

            /** @return array<Field> */
            public function fields(NovaRequest \$request): array
            {
                return [
                    ...\$this->getOneOffScriptInfoFields(),
                    Boolean::make('Dry run', 'dry-run')->default(true),
                ];
            }

            public function handle(ActionFields \$fields): ActionResponse
            {
                \$isDryRun = \$fields->boolean('dry-run');

                \$this->logger->debug(
                    sprintf('Executing one-time script %s', \$this->getOneOffScriptSlug()),
                    [
                        LoggingContextKeys::ONE_OFF_SCRIPT => \$this->getOneOffScriptSlug(),
                        LoggingContextKeys::META => ['dry-run' => \$isDryRun],
                    ]
                );

                if (\$isDryRun) {
                    // TODO: Implement dry-run logic here.

                    return self::message('Dry run completed.');
                }

                // TODO: Implement one-off script logic here.

                \$this->registerExecution();

                return self::message('One-off script executed successfully.');
            }

            protected function getOneOffScriptSlug(): string
            {
                return self::SLUG;
            }

            protected function getOneOffScriptTicketUrl(): string
            {
                return '{$ticketUrl}';
            }
        }

        PHP;
    }

    private function generateTestStub(string $name, string $className): string
    {
        $fullClassName = sprintf('Waterfront\\Apps\\OneOffScripts\\%s\\%s', $name, $className);

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace Tests\Apps\OneOffScripts\\{$name};

        use Illuminate\Support\Collection;
        use Laravel\Nova\Actions\Responses\Message as NovaMessage;
        use Laravel\Nova\Fields\ActionFields;
        use PHPUnit\Framework\Attributes\CoversClass;
        use Psr\Log\LoggerInterface;
        use Tests\IntegrationTestCase;
        use {$fullClassName};

        #[CoversClass({$className}::class)]
        class {$className}Test extends IntegrationTestCase
        {
            private LoggerInterface \$logger;

            protected function setUp(): void
            {
                parent::setUp();

                \$this->logger = self::createStub(LoggerInterface::class);
            }

            public function testHandleDryRun(): void
            {
                \$action = new {$className}(
                    logger: \$this->logger,
                );

                \$actionFields = new ActionFields(
                    new Collection(['dry-run' => true]),
                    new Collection(),
                );

                \$actionResponse = \$action->handle(\$actionFields);

                \$responseData = \$actionResponse->jsonSerialize();
                self::assertArrayHasKey('message', \$responseData);
                self::assertInstanceOf(NovaMessage::class, \$responseData['message']);
                self::assertSame('Dry run completed.', (string) \$responseData['message']);
            }

            public function testHandle(): void
            {
                \$action = new {$className}(
                    logger: \$this->logger,
                );

                \$actionFields = new ActionFields(
                    new Collection(['dry-run' => false]),
                    new Collection(),
                );

                \$actionResponse = \$action->handle(\$actionFields);

                \$responseData = \$actionResponse->jsonSerialize();
                self::assertArrayHasKey('message', \$responseData);
                self::assertInstanceOf(NovaMessage::class, \$responseData['message']);
                self::assertSame('One-off script executed successfully.', (string) \$responseData['message']);
            }
        }

        PHP;
    }
}
