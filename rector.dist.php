<?php

declare(strict_types=1);

use Rector\Arguments\Rector\ClassMethod\ArgumentAdderRector;
use Rector\CodeQuality\Rector\If_\CompleteMissingIfElseBracketRector;
use Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector;
use Rector\CodingStyle\Rector\FuncCall\FunctionFirstClassCallableRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;
use Rector\DeadCode\Rector\Plus\RemoveDeadZeroAndOneOperationRector;
use Rector\Php53\Rector\Ternary\TernaryToElvisRector;
use Rector\Php74\Rector\Property\RestoreDefaultNullToNullableTypePropertyRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitSelfCallRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\YieldDataProviderRector;
use Rector\Privatization\Rector\Class_\FinalizeTestCaseClassRector;
use RectorLaravel\Rector\Class_\AppendsPropertyToAppendsAttributeRector;
use RectorLaravel\Rector\Class_\FillablePropertyToFillableAttributeRector;
use RectorLaravel\Rector\Class_\HiddenPropertyToHiddenAttributeRector;
use RectorLaravel\Rector\Class_\TablePropertyToTableAttributeRector;
use RectorLaravel\Rector\ClassMethod\MigrateToSimplifiedAttributeRector;
use RectorLaravel\Rector\PropertyFetch\ReplaceFakerInstanceWithHelperRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/config',
        __DIR__ . '/database',
        __DIR__ . '/routes',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withRules([
        // We use self::assert instead of $this->assert that is the default.
        PreferPHPUnitSelfCallRector::class,
        CompleteMissingIfElseBracketRector::class,
    ])
    ->withSkip([
        // phpstan: Short ternary operator is not allowed. Use null coalesce operator if applicable or consider using long ternary.
        TernaryToElvisRector::class,
        // Adding default parameters to function calls are not very readable if the parameter name is not added.
        ArgumentAdderRector::class,
        // We use self::assert instead of $this->assert that is the default.
        PreferPHPUnitThisCallRector::class,

        // These needs to be enabled in a new MR as it changes too much for now.
        YieldDataProviderRector::class,

        // This removes too much and breaks the code.
        RemoveDeadZeroAndOneOperationRector::class => [
            __DIR__ . '/src/Infra/PasswordGenerator/AbstractGenerator.php',
            __DIR__ . '/src/Domain/Ferry/Jobs/TechnicalDomainMigrationJob.php',
        ],

        // The annotation is correct since the foreign key is nullable in the database. It's just wrong in the model definition.
        RemoveNonExistingVarAnnotationRector::class => [
            __DIR__ . '/src/Domain/Ferry/Jobs/TechnicalDomainMigrationJob.php',
        ],

        ReadOnlyPropertyRector::class => [
            // $foundCharacters shouldn't be readonly as it is being mutated.
            __DIR__ . '/src/Support/Helpers/ValidationRules/FilterSpecialChars.php',
        ],

        // The callables that we use are generally going into framework functionality. They are more readable in the non first class callable form.
        FunctionFirstClassCallableRector::class,
        ArrowFunctionDelegatingCallToFirstClassCallableRector::class,

        // This rule currently blows up our CI. Having final test classes is less important than the effort required to fix the CI.
        FinalizeTestCaseClassRector::class,

        // We prefer to keep the fillable, table, hidden, append properties over the PHP 8 attribute syntax.
        FillablePropertyToFillableAttributeRector::class,
        TablePropertyToTableAttributeRector::class,
        AppendsPropertyToAppendsAttributeRector::class,
        HiddenPropertyToHiddenAttributeRector::class,

        // Acronis request DTOs rely on a strict distinction between implicit vs explicit null
        RestoreDefaultNullToNullableTypePropertyRector::class => [
            __DIR__ . '/src/Infra/AcronisClient/DTO/Requests',
            __DIR__ . '/src/Infra/AcronisClient/DTO/Tenants',
        ],

        // The project requires factory scoped Faker instead of the global helper.
        ReplaceFakerInstanceWithHelperRector::class,

        // Accessors must be migrated together with direct callers and PHPStan generic types.
        MigrateToSimplifiedAttributeRector::class,
    ])
    ->withComposerBased(
        phpunit: true,
        laravel: true,
    )
    ->withPreparedSets(
        deadCode: true,
        phpunitCodeQuality: true,
    )
    ->withPhpSets()
    ->withParallel(timeoutSeconds: 360, maxNumberOfProcess: 7)
    ->withImportNames();
