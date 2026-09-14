<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Services\ManualMigration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ferry\Enums\ManualMigrationOption;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Services\ManualMigration\TechnicalSteps;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Support\Exceptions\NotImplementedException;

#[CoversClass(TechnicalSteps::class)]
class TechnicalStepsTest extends IntegrationTestCase
{
    private TechnicalSteps $technicalStepsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->technicalStepsService = self::resolve(TechnicalSteps::class);
    }

    #[Test]
    public function calculateDomainSteps(): void
    {
        $options = [
            ManualMigrationOption::DNSSEC_ENABLE,
            ManualMigrationOption::NAMESERVERS_UPDATE_NEW,
            ManualMigrationOption::DNS_NEW_EMPTY,
        ];

        $steps = $this->technicalStepsService->getSteps(ProductGroupType::EXTENSION, $options, true);

        self::assertSame(
            [
                MigrationStep::DOMAIN_MIGRATION,
                MigrationStep::CONFIGURE_DNS_EMPTY_ZONE,
                MigrationStep::NAMESERVER_SET_DEFAULT,
                MigrationStep::ENABLE_DNSSEC,
            ],
            $steps,
        );
    }

    #[Test]
    public function calculateHostingSteps(): void
    {
        $steps = $this->technicalStepsService->getSteps(ProductGroupType::HOSTING, null);

        self::assertSame([MigrationStep::HOSTING_MIGRATION], $steps);
    }

    #[Test]
    public function unimplementedProductGroupBreaks(): void
    {
        self::expectException(NotImplementedException::class);

        $this->technicalStepsService->getSteps(ProductGroupType::MICROSOFT_365, null);
    }
}
