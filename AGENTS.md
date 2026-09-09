# Foundational Context

This application is a Laravel application using Domain Driven Design (DDD) and its main packages are below, for specific versions check the `composer.json` and/or `composer.lock`. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php
- laravel/framework (LARAVEL)
- laravel/nova (NOVA)
- larastan/larastan (LARASTAN)
- phpunit/phpunit (PHPUNIT)
- rector/rector (RECTOR)

## Agent Skills
Specific agent skills to help you do work can be found in `.agents/skills`.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

# Comments and docblocks

- Do not write comments or docblocks that restate what the code, the class name, or the method name already says. A docblock whose prose is derivable from the signature is noise; delete it rather than write it.
- Keep type-carrying annotations. `@param`, `@return`, `@var` and array-shape types are load-bearing for static analysis and must stay, including on methods that get no prose.
- A comment is worth writing only when it carries something the reader cannot get from the code: a non-obvious constraint, a workaround and its reason, an ordering requirement, an external quirk, or the consequence of getting it wrong.
- This applies to every language and to template files too, not just PHP docblocks.

## Domain Driven Design (DDD)
- Follow the DDD structure and principles. Place files in the correct directories according to their domain and layer (Domain, Apps, Infra, Support).
- Break Laravel conventions when they conflict with DDD principles. For example, do not create controllers or Eloquent models if they don't fit the DDD structure. Instead, create services, actions, repositories, etc., that fit the DDD architecture.
- When creating new features, think about the domain and how to model it effectively. Use DTOs, entities, aggregates, and repositories as needed to represent the domain accurately.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## No Frontend

- This application does not have a frontend. Do not create or edit any files in `resources/js` or `resources/css`. Do not write any JavaScript, CSS, or Blade code. Focus on backend PHP code and tests.

## Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.
- Make sure models are placed in the correct directory according to the DDD structure. If the model is related to a specific domain, it should be placed in the `Domain` directory of that domain such as `src/Domain/Order/Models/` for Models related to orders.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.
- Documentation Files should be concise and focused on the specific feature or change. Do not create general documentation files that cover broad topics or the entire application. Focus on the specific change or feature you are working on.
- When creating documentation files, use clear and descriptive titles that accurately reflect the content of the file
- Documentation files should be located in the `docs/` directory and organized in a way that makes it easy for users to find relevant information. Use subdirectories if necessary to group related documentation together.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- Tests should include a `CoverClass` annotation for the class being tested.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.
- Unit tests should mock their dependencies and only test the logic of the class being tested. Integrations tests can be more integrated and test multiple classes working together, but should still focus on testing a specific feature or behavior.
- Integration tests extend the `tests/IntegrationTestCase` and should be placed in a `Integration` subdirectory per Domain.
- Unit tests extend the `tests/TestCase`. These should be placed according to PSR-4 autoloader rules in the `tests/` directory, with subdirectories per Domain. Example: `tests/Domain/Backup/Services/BackupServiceTest.php` for a unit test of the `src/Domain/Backup/Services/BackupService.php` service in the Backup domain.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests use the composer script: `composer test`.
- To run all tests in a single file: `php artisan test -p --no-coverage --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test -p --no-coverage --compact --filter=testName` (recommended after making a change to a related file).

# PHP Coding Standards

## General

### PHP-FIG Standards

Follow the [PHP-FIG (PHP Framework Interop Group)](https://www.php-fig.org/) standards.

### Nullable Parameters in Functions

Nullable parameters are allowed, but **never** with a default value.

**Don't:**
```php
class Foo
{
    public function bar(public ?string $baz = null)
    {
    }
}
```

**Do:**
```php
class Foo
{
    public function bar(public ?string $baz)
    {
    }
}
```

### Use Dependency Injection Where Possible

Don't instantiate classes with `new` in the constructor. Let dependency injection provide the instantiated classes — preferably an interface that resolves to a derived class.

Benefits:
- Requirements of the class/service are clear
- Dependencies can be replaced (with mocks) when testing
- Refactoring becomes a lot easier
- Static analysers understand what's going on

**Don't:**
```php
class Foo
{
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = new Logger();
    }
}
```

**Do:**
```php
class Foo
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }
}
```

### Describe Your Test Cases in the Function Methods

Describing what the test case is testing helps future readers understand what is happening.

**Don't:**
```php
class FooTest extends TestCase
{
    public function testFoo();
}
```

**Do:**
```php
class FooTest extends TestCase
{
    public function testFooWillReturnValidResponse();
}
```

### Configuration Should Be Injected from a Service Provider, Not Fetched

Getting configuration values directly in a service makes it difficult to test with different configuration values and hides dependencies. Inject configuration values in the constructor instead.

- **Laravel specific:** Fetching configuration values in a service provider ensures configuration is loaded when the application boots.
- **Waterfront specific:** When it's not possible to inject configuration values, injecting or resolving the `ConfigurationInterface` service is an accepted workaround.

**Don't:**
```php
class Foo
{
    public function __construct()
    {
        $this->foo(config('app.randomsetting'));
    }
}
```

**Do:**
```php
class Foo
{
    public function __construct(private string $randomSetting)
    {
        $this->foo($this->randomSetting);
    }
}

class AppProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(Foo::class, fn () => new Foo(config('app.randomsetting')));
    }
}
```

### Create Unit Tests for All Logic in All Services

For each public method in a service there should be:
- One or more **happy flow** tests
- One or more **failure flow** tests

All logic in the service should be covered with a test. Make sure you test with multiple inputs.

### PHP Enum Keys in Capitals

PHP enum keys must be written in **UPPER_CASE**. This follows the same pattern as class constants, making them distinguishable from normal variables.

**Don't:**
```php
enum Foo
{
    case no_lowercase_keys;
}
```

**Do:**
```php
enum Foo
{
    case KEY_IN_UPPERCASE;
}
```

### Do Not Communicate Database IDs Outside Your Application

Database IDs are for internal relations between entities in your persistence layer. If your entity must be uniquely identified outside your application, use a **UUID** instead.

**Don't:**
```php
class PriceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
        ];
    }
}
```

**Do:**
```php
class PriceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->resource->uuid,
        ];
    }
}
```

---

## Laravel

### Model Creation and Setting Properties

Do **not** set the `$fillable` array on models. Not setting it restricts mass assignment and forces the use of PHP class properties, improving type-hinting and making usages easier to find for static code analysers.

**Don't:**
```php
MyModel extends Model {
    protected $fillable = [
        'field1',
        'field2'
    ];
}

$myModel = Model::create([
    'field1' => 'foo',
    'field2' => 123
]);
```

**Do:**
```php
/**
 * @property string $field1
 * @property int    $field2
 */
MyModel extends Model {
}

$myModel = new MyModel();
$myModel->field1 = 'foo';
$myModel->field2 = 123;
$myModel->save();
```

### Controllers Should Not Inherit

Don't create a common base class for controllers serving similar purposes across different APIs. As time goes on, use cases for the two APIs will diverge. Instead, reduce code duplication by adding an action to a domain module (or a service class, or external service, etc.).

**Don't:**
```php
abstract class CommonLoremController {
    public function index(): LoremResource
    {
        // Lorem query logic
        return LoremResource::collection($lorems);
    }
}

class ApiALoremController extends CommonLoremController {}
class ApiBLoremController extends CommonLoremController {}
```

**Do:**
```php
class ApiALoremController {
    public function __construct(
        private FetchLoremsAction $fetchLoremsAction,
    ) {}

    public function index(): LoremResource
    {
        return LoremResource::collection(
            $this->fetchLoremsAction->execute()
        );
    }
}

// idem for ApiBLoremController

namespace Waterfront\Domain\Lorem\Actions;

class FetchLoremsAction {
    public function execute()
    {
        // Lorem query logic
        return $lorems;
    }
}
```

### Prefix Class Names for Nova Resources / Actions with `Nova`

Nova resources and Nova-specific actions are not often touched in day-to-day work. Prefix their class names with `Nova` and suffix them with the correct type (e.g., `Resource`, `Action`) so they are easy to recognize outside the Nova folder and don't clutter IDE search results.

**Don't:**
```php
use App\Nova\Resource;
use Models\DomainContact as DomainContactModel;

class DomainContact extends Resource
{
    public static $model = DomainContactModel::class;
}
```

**Do:**
```php
use App\Nova\Resource;
use Models\DomainContact;

class NovaDomainContactResource extends Resource
{
    public static $model = DomainContact::class;
}
```

### DTO Class Naming + Readonly

Suffix DTO classes with `DTO` to distinguish them from Model classes (also helps with debugging in Tinker). Since PHP 8.2, make the entire class `readonly` if all properties can be readonly — this also prevents properties from being added at runtime.

**Don't:**
```php
class Customer
{
    public function __construct(
        public readonly string $firstName,
    ) {}
}
```

**Do:**
```php
readonly class CustomerDTO
{
    public function __construct(
        public string $firstName,
    ) {}
}
```

### Use `self::translate` in Nova Resources for Translations

Use `self::translate()` for translations in Nova resources. This is **not** relevant for Nova metrics, actions, fields, and filters.

**Don't:**
```php
class NovaVouchersResource extends Resource
{
    public static function group(): string
    {
        return strval(trans('nova-group.invoices'));
    }

    public function fields(Request $request): array
    {
        return [
            Text::make(
                trans('voucher.attributes.display_name'),
                'display_name'
            )->help(strval(trans('voucher.attributes.apply_with_discount_help')))
        ];
    }
}
```

**Do:**
```php
class NovaVouchersResource extends Resource
{
    public static function group(): string
    {
        return self::translate('nova-group.invoices');
    }

    public function fields(Request $request): array
    {
        return [
            Text::make(
                self::translate('voucher.attributes.display_name'),
                'display_name'
            )->help(self::translate('voucher.attributes.apply_with_discount_help'))
        ];
    }
}
```

### Use Repositories for Database Reading

Use a repository for reading information from a database. Direct model calls are acceptable only if the repository pattern is not technically feasible.

Benefits:
- Prevents duplication of queries
- Easier refactoring by finding classes that use a specific repository or query
- Avoids forgetting to update all queries when a condition is altered
- Performance-related changes become easier
- Enables mocking in tests

**Don't:**
```php
class SomeService
{
    public function doSomethingForCustomer(int $customerId)
    {
        $customer = Customer::where(['active' => true, 'id' => $customerId])->first();

        // Some logic with that customer
    }
}
```

**Do:**
```php
class CustomerRepository
{
    public function getActiveCustomer(int $customerId): ?Customer
    {
        return Customer::where(['active' => true, 'id' => $customerId])->first();
    }
}

class SomeService
{
    public function __construct(
        private readonly CustomerRepository $customerRepository
    ) {
    }

    public function doSomethingForCustomer(int $customerId)
    {
        $customer = $this->customerRepository->getActiveCustomer($customerId);

        // Some logic with that customer
    }
}
```

---

## Testing

### Ensure Logging Statements Are Called with the Proper Parameters

If you identify a place where information should be logged for debugging purposes, make sure you also **test** that logging statement — including checking that the important information is correctly passed in.

**Don't** (missing logger assertion):
```php
// Production code
$domainList = $this->determineEligibleDomains();
$logger->info('Found eligible domains for processing', ['domains' => $domains]);
$domainProcessor->process($domains);

// Unit test
self::createMock(DomainProcessor::class)
    ->expects(self::once())->method('process')
    ->with($expectedDomainList);
```

**Do:**
```php
// Production code
$domainList = $this->determineEligibleDomains();
$logger->info('Found eligible domains for processing', ['domains' => $domains]);
$domainProcessor->process($domains);

// Unit test
self::createMock(DomainProcessor::class)
    ->expects(self::once())->method('process')
    ->with($expectedDomainList);

$loggerMock = self::createMock(LoggerInterface::class);
$loggerMock->expects(self::once())
    ->method('info')
    ->with('Found eligible domains for processing', ['domains' => $expectedDomainList]);
```

### Don't Use Log Assertions as the Only Way to Check a Code Path Was Executed

Factor your code so that end conditions can be verified by your tests. Logging is not supposed to be central to your code's logic. You can still check log statements, but don't rely on them as the **only** verification.

**Don't:**
```php
// Unit test
$domainList = [];
$loggerMock = self::createMock(LoggerInterface::class);
$loggerMock->expects(self::once())
    ->method('warning')
    ->with('No eligible domains found');
```

**Do:**
```php
// Unit test
$domainList = [];
self::createMock(DomainProcessor::class)
    ->expects(self::never())
    ->method('process');

$loggerMock = self::createMock(LoggerInterface::class);
$loggerMock->expects(self::once())
    ->method('warning')
    ->with('No eligible domains found');
```

### Use `self::assert*` Methods in PHPUnit Test Cases

For consistency, always use `self::assert*()` — not `$this->assert*()` or fully-qualified `Assert::assert*()`.

**Don't:**
```php
use \PHPUnit\Framework\Assert;

class SomeTest extends \PHPUnit\TestCase
{
    public function testSomeFunctionality(): void
    {
        $this->assertSame('x', 'x');
        Assert::assertSame('x', 'x');
        \PHPUnit\Framework\Assert::assertSame('x', 'x');
    }
}
```

**Do:**
```php
class SomeTest extends \PHPUnit\TestCase
{
    public function testSomeFunctionality(): void
    {
        self::assertSame('x', 'x');
        self::assertTrue(true);
    }
}
```

### Testing DTOs / Models

DTOs and Eloquent Models should ideally not contain any logic, so don't write direct tests for them. Instead, test that they work correctly by testing a **service that uses them**. If a DTO/Model does contain logic, then adding a unit test for it is fine.

**Don't:**
```php
class SomeTest extends \PHPUnit\TestCase
{
    public function testDTOWorksLikeIntended(): void
    {
        $dto = new SomeDTO(111);
        self::assertSame($dto->someProperty, 111);
    }
}
```

**Do:**
```php
class SomeService
{
    public function something(int $someInput): SomeDTO
    {
        return new SomeDTO($someInput * 2);
    }
}

class SomeTest extends \PHPUnit\TestCase
{
    public function testSomethingReturnsDTOWithMultipliedInput(): void
    {
        $service = new SomeService();
        $result = $service->something(11);
        self::assertSame(22, $result->someProperty);
    }
}
```


# Logging Standards

Standards for writing log messages used for monitoring and tracing the platform and processes.

> This document focuses on PHP back-end scripts writing persistent (sys)log messages. On the PHP backend we use Monolog as the concrete implementation of `Psr\Log\LoggerInterface`.

## Main takeaways for writing log messages

1. **Think about traceability** of the processes you create/manage.
    - What are the process-critical steps in code that you want to trace through logging.
    - What are the business-relevant events that you want to trace through logging.
2. **Keep your log message short** — try to stay under 12 words.
3. **Avoid multiple variables within the log message** or place them at the end.
    - This increases scannability of message lists.
    - The context is a better place for variable values.
4. **Add useful context** to the log message.
    - Always use the same context key for the same type of value.
5. **Decide on the correct log level** for each log message.
    - See the log levels section below.

---

## Traceability Through Logging

By adding logs to requests, commands, handlers, and process steps we enable tracing entire front-end mouse clicks to the back-end, including all triggered process steps, business events, and entity/model lifecycle events. By digesting all these logs in tools like OpenSearch (Insights) we enable debugging with more context, visualize trends, and detect possible problems proactively.

---

## Log Messages & Context

Keep log messages short and without too many variables. Try to stay under 12-word sentences.

Use the **context** to keep messages short and add additional information. In OpenSearch, each separate context key is a separate searchable field that can be displayed next to the message in overviews or detail views.

### Automatically Added Context (Monolog Processors)

| Processor | Description |
|---|---|
| **UuidProcessor** (Monolog) | Adds a unique `uid` value to each log message; the same value is used within a single request/process. |
| **IntrospectionProcessor** (Monolog) | Adds the class, function, and line the log message was written from. |
| **ThrowableExceptionContextProcessor** (custom) | When logging an exception with `['exception' => $caughtException]`, this processor extracts data from the exception into standard context properties. |
| **PsrLogMessageProcessor** (Monolog) | Applies string replacement on the log message using context key-values, based on PSR-3 rules. |

### Context Keys

To provide a convenient overview of standardized context keys, a `LoggingContextKeys` support class is available (in Waterfront).

```php
$this->logger->info(
    'Action performed for subscription {subscription.id}: {domain.name}', [
        LoggingContextKeys::SUBSCRIPTION_ID => 123,
        LoggingContextKeys::DOMAIN_NAME => 'sandwave.io',
        LoggingContextKeys::CUSTOMER_ID => 234
    ]
);
```

By using standardized context keys, tools like OpenSearch (Insights) can parse the log messages and searching for specific messages becomes easier.

> **OpenSearch field mapping:** To improve findability and performance, a field mapping is configured in OpenSearch so it knows which fields should contain what type. If a context field is mapped as text, OpenSearch cannot index values for that field that are not text. For each new `LoggingContextKey` that is added, the mapping config in OpenSearch must be updated as well. See [OpenSearch field types](https://opensearch.org/docs/latest/field-types/). A mapping config file will become available in the infra repo.

### Context Key Format

Context keys must adhere to a strict format so that processing within Insights does not fail.

**Rules:**
- Root context keys must be constructed of **two parts separated by a dot**: `<domain>.<field>`
    - Part 1: domain name, model name, subject, or technical process name
    - Part 2: field or property name the value references
- Examples: `subscription.id`, `customer.number`, `provisioning.id`
- Names can use **snake_case** if necessary (e.g., `product_group`)
- Sub keys in meta context follow the same rules
- All key naming must be **snake_case in lowercase** with sub parts separated by `.`

### Example

**Don't:**
```php
$logger->info(sprintf(
    '[%s] - Action performed for domain %s with subscription'
        . '%d and customer id %d: %s',
    self::class,
    'sandwave.io',
    123,
    234,
    'Some response code'
));
```

**Do:**
```php
$this->logger->info(
    'Action performed for subscription {subscription.id}: {domain.name}', [
        LoggingContextKeys::SUBSCRIPTION_ID => 123,
        LoggingContextKeys::DOMAIN_NAME => 'sandwave.io',
        LoggingContextKeys::CUSTOMER_ID => 234
    ]
);
```

---

## Log Levels

Monolog uses log levels based on RFC 5424. Use the following guidelines to decide which level to use:

| Level | Name | Usage | Example |
|---|---|---|---|
| 100 | **DEBUG** | Detailed debug information. | Can live without these on production. |
| 200 | **INFO** | Interesting events. System reporting and measuring messages. | User authenticated, sub-process steps, start/end of cron/request/message handler. |
| 250 | **NOTICE** | Normal but significant (business-relevant) events. | Order received, subscription renewed, invoice created, technical provisioning completed. |
| 300 | **WARNING** | Exceptional occurrences that are not errors. | Undesirable things that are not necessarily wrong. The process can continue without error (self-recoverable). |
| 400 | **ERROR** | Runtime errors that do not require immediate action but should be logged and monitored. | Authentication errors, API timeouts, unexpected but caught and handled errors. These can indicate locations where processes or user actions can be optimized. |
| 500 | **CRITICAL** | Critical conditions. | Process-blocking errors/exceptions, uncaught exceptions. Developer action is required to research and possibly make a bugfix. |

> **Do not use ALERT (550) or EMERGENCY (600).** These levels make less sense from a process logging perspective.
