<?php

declare(strict_types=1);

namespace Tests\Infra\RtrClient\Services;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\TranslationKeyFactory;
use Tests\Factories\TranslationLanguageFactory;
use Tests\IntegrationTestCase;
use Waterfront\Infra\RtrClient\Enums\RtrValidationError;
use Waterfront\Infra\RtrClient\Services\RtrErrorParseService;
use Waterfront\Infra\Translation\Translator;

#[CoversClass(RtrErrorParseService::class)]
#[AllowMockObjectsWithoutExpectations]
class RtrErrorParseServiceTest extends IntegrationTestCase
{
    private RtrErrorParseService $errorParseService;

    private LoggerInterface&MockObject $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLogger = self::createMock(LoggerInterface::class);

        $this->errorParseService = new RtrErrorParseService(
            self::resolve(Translator::class),
            $this->mockLogger,
        );
    }

    #[Test]
    public function getTranslatedRtrErrorUnknownError(): void
    {
        $unknownError = 'This is made up, this is not a real RTR error message at all.';

        self::assertSame(
            'rtr-error.general',
            $this->errorParseService->getTranslatedRtrError($unknownError),
        );
    }

    #[Test]
    public function getTranslatedRtrErrorKnownError(): void
    {
        $knownError = 'Transfer is not possible for a domain with statuses \'[CLIENT_TRANSFER_PROHIBITED, OK]\'';

        self::assertSame(
            'rtr-error.transfer-blocked',
            $this->errorParseService->getTranslatedRtrError($knownError),
        );
    }

    #[Test]
    public function getTranslatedRtrErrorKnownErrorWithDate(): void
    {
        $languageNL = new TranslationLanguageFactory()->createOne([
            'display_name' => 'netherlands',
            'locale' => 'nl',
            'active' => false,
            'default' => false,
        ]);

        new TranslationKeyFactory()
            ->withTranslatedString($languageNL, 'test :date')
            ->create([
                'key' => 'rtr-error.transfer-too-early-with-date',
                'source' => 'waterfront',
            ]);

        $knownError = 'Transfer is not possible for domains that have been registered in the past 60 days; eligible for transfer on 2023-11-08';

        self::assertSame(
            'test 2023-11-08',
            $this->errorParseService->getTranslatedRtrError($knownError),
        );
    }

    #[Test]
    public function testGetRtrErrorFromMessageReturnsError(): void
    {
        $message = 'Some error message containing ' . RtrValidationError::TRANSFER_TOO_EARLY->value;
        $result = $this->errorParseService->getRtrErrorFromMessage($message);

        $this->mockLogger->expects(self::never())->method('warning');

        self::assertSame(RtrValidationError::TRANSFER_TOO_EARLY, $result);
    }

    #[Test]
    public function testGetRtrErrorFromMessageReturnsNull(): void
    {
        $message = 'Some unknown error message';

        $this->mockLogger
            ->expects(self::once())
            ->method('warning')
            ->with(
                sprintf(
                    'Unable to parse (unknown) RTR error message [%s]. Returning default.',
                    $message,
                ),
            );

        $result = $this->errorParseService->getRtrErrorFromMessage($message);

        self::assertNull($result);
    }

    #[Test]
    public function stacktraceShouldReturnGeneralMessage(): void
    {
        $message = <<<STACKTRACE
        {"message":"Domain registration failed","exception":"Domain registration status: FAI. Reason: Neem contact op met onze helpdesk om te controleren welke aanvullende actie nodig is.","trace":"#0 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Container\/BoundMethod.php(36): Waterfront\\Domain\\Provision\\Extensions\\Jobs\\RegisterDomainJob->handle()\n#1 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Container\/Util.php(41): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#2 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Container\/BoundMethod.php(93): Illuminate\\Container\\Util::unwrapIfClosure()\n#3 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Container\/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod()\n#4 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Container\/Container.php(662): Illuminate\\Container\\BoundMethod::call()\n#5 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Bus\/Dispatcher.php(128): Illuminate\\Container\\Container->call()\n#6 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Pipeline\/Pipeline.php(144): Illuminate\\Bus\\Dispatcher->Illuminate\\Bus\\{closure}()\n#7 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Pipeline\/Pipeline.php(119): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()\n#8 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Bus\/Dispatcher.php(132): Illuminate\\Pipeline\\Pipeline->then()\n#9 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Queue\/CallQueuedHandler.php(123): Illuminate\\Bus\\Dispatcher->dispatchNow()\n#10 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Pipeline\/Pipeline.php(144): Illuminate\\Queue\\CallQueuedHandler->Illuminate\\Queue\\{closure}()\n#11 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Pipeline\/Pipeline.php(119): Illuminate\\Pipeline\\Pipeline->Illuminate\\Pipeline\\{closure}()\n#12 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Queue\/CallQueuedHandler.php(122): Illuminate\\Pipeline\\Pipeline->then()\n#13 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Queue\/CallQueuedHandler.php(70): Illuminate\\Queue\\CallQueuedHandler->dispatchThroughMiddleware()\n#14 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Queue\/Jobs\/Job.php(102): Illuminate\\Queue\\CallQueuedHandler->call()\n#15 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Queue\/Worker.php(439): Illuminate\\Queue\\Jobs\\Job->fire()\n#16 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Queue\/Worker.php(389): Illuminate\\Queue\\Worker->process()\n#17 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Queue\/Worker.php(176): Illuminate\\Queue\\Worker->runJob()\n#18 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Queue\/Console\/WorkCommand.php(137): Illuminate\\Queue\\Worker->daemon()\n#19 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Queue\/Console\/WorkCommand.php(120): Illuminate\\Queue\\Console\\WorkCommand->runWorker()\n#20 \/srv\/www\/vendor\/laravel\/horizon\/src\/Console\/WorkCommand.php(51): Illuminate\\Queue\\Console\\WorkCommand->handle()\n#21 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Container\/BoundMethod.php(36): Laravel\\Horizon\\Console\\WorkCommand->handle()\n#22 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Container\/Util.php(41): Illuminate\\Container\\BoundMethod::Illuminate\\Container\\{closure}()\n#23 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Container\/BoundMethod.php(93): Illuminate\\Container\\Util::unwrapIfClosure()\n#24 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Container\/BoundMethod.php(35): Illuminate\\Container\\BoundMethod::callBoundMethod()\n#25 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Container\/Container.php(662): Illuminate\\Container\\BoundMethod::call()\n#26 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Console\/Command.php(211): Illuminate\\Container\\Container->call()\n#27 \/srv\/www\/vendor\/symfony\/console\/Command\/Command.php(326): Illuminate\\Console\\Command->execute()\n#28 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Console\/Command.php(180): Symfony\\Component\\Console\\Command\\Command->run()\n#29 \/srv\/www\/vendor\/symfony\/console\/Application.php(1096): Illuminate\\Console\\Command->run()\n#30 \/srv\/www\/vendor\/symfony\/console\/Application.php(324): Symfony\\Component\\Console\\Application->doRunCommand()\n#31 \/srv\/www\/vendor\/symfony\/console\/Application.php(175): Symfony\\Component\\Console\\Application->doRun()\n#32 \/srv\/www\/vendor\/laravel\/framework\/src\/Illuminate\/Foundation\/Console\/Kernel.php(201): Symfony\\Component\\Console\\Application->run()\n#33 \/srv\/www\/artisan(35): Illuminate\\Foundation\\Console\\Kernel->handle()\n#34 {main}"}
        STACKTRACE;

        self::assertSame(
            'rtr-error.general',
            $this->errorParseService->getTranslatedRtrError($message),
        );
    }
}
