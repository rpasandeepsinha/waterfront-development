<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Services;

use Iterator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\Hosting\DirectAdmin\Services\DirectAdminHostingService;
use Waterfront\Domain\Hosting\DTO\UserStatistics;
use Waterfront\Domain\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Hosting\Services\HostingDowngradePossibilityChecker;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\DirectAdminClient\DTO\DirectAdminUserPackage;

#[CoversClass(HostingDowngradePossibilityChecker::class)]
#[AllowMockObjectsWithoutExpectations]
class HostingDowngradePossibilityCheckerTest extends TestCase
{
    #[Test]
    #[DataProvider('provideScenariosForDowngrade')]
    public function canDowngradeToServicePlanSucceeds(UserStatistics $userStats, bool $expectedToSucceed): void
    {
        $directAdminUserPackage = new DirectAdminUserPackage(
            vdomains: '2',
            nemails: '2',
            mysql: '2',
            bandwidth: '1024',
            quota: '1024',
            package: 'pakketHaalbareKaart',
        );

        $stubHostingDeploymentRepository = self::createStub(HostingDeploymentRepository::class);
        $stubHostingDeploymentRepository->method('getServer')->willReturn(self::createStub(Server::class));

        $stubDirectAdminHostingService = self::createStub(DirectAdminHostingService::class);
        $stubDirectAdminHostingService->method('getUserStats')->willReturn($userStats);
        $stubDirectAdminHostingService->method('getPackageOnServerAsDto')->willReturn($directAdminUserPackage);

        $stubHostingServiceFactory = self::createStub(HostingServiceFactory::class);
        $stubHostingServiceFactory->method('getDriverFromServer')->willReturn(ProviderSlug::DIRECTADMIN);
        $stubHostingServiceFactory->method('driver')->willReturn($stubDirectAdminHostingService);

        $stubHostingService = self::createStub(HostingService::class);
        $stubHostingService->method('getPackageOnServerAsDto')->willReturn($directAdminUserPackage);
        $stubHostingService->method('getUserStats')->willReturn($userStats);

        $subSubscriptionRepository = self::createStub(SubscriptionRepository::class);
        $subSubscriptionRepository
            ->method('getSubscriptionByHostingDeployment')
            ->willReturn(self::createStub(Subscription::class));

        $checker = new HostingDowngradePossibilityChecker(
            $stubHostingServiceFactory,
            $stubHostingDeploymentRepository,
            $stubHostingService,
            $subSubscriptionRepository,
        );

        $hostingDeployment = self::createStub(HostingDeployment::class);
        $hostingDeployment->subscription = self::createStub(Subscription::class);
        $servicePlan = 'pakketHaalbareKaart';

        self::assertSame(
            $expectedToSucceed,
            $checker->canDowngradeToServicePlan(
                $hostingDeployment,
                $servicePlan,
            )->isSuccessful,
        );
    }

    public static function provideScenariosForDowngrade(): Iterator
    {
        yield 'Current usage allows the downgrade ' => [
            new UserStatistics(
                activeDomains: 1,
                subdomains: 1,
                diskSpaceInMb: 1024,
                mailDiskSpaceInMb: 1024,
                mailBoxes: 1,
                mailLists: 0,
                mailAutoResponders: 0,
                redirects: 0,
                databases: 0,
                traffic: 0,
            ),
            true,
        ];
        yield 'Too many active domains prohibit the downgrade ' => [
            new UserStatistics(
                activeDomains: 3,
                subdomains: 1,
                diskSpaceInMb: 1024,
                mailDiskSpaceInMb: 1024,
                mailBoxes: 1,
                mailLists: 0,
                mailAutoResponders: 0,
                redirects: 0,
                databases: 0,
                traffic: 0,
            ),
            false,
        ];
        yield 'Too many mailboxes prohibit the downgrade ' => [
            new UserStatistics(
                activeDomains: 2,
                subdomains: 1,
                diskSpaceInMb: 1024,
                mailDiskSpaceInMb: 1024,
                mailBoxes: 3,
                mailLists: 0,
                mailAutoResponders: 0,
                redirects: 0,
                databases: 0,
                traffic: 0,
            ),
            false,
        ];
        yield 'Too many disk usage prohibit the downgrade ' => [
            new UserStatistics(
                activeDomains: 3,
                subdomains: 1,
                diskSpaceInMb: 1024,
                mailDiskSpaceInMb: 2048,
                mailBoxes: 1,
                mailLists: 0,
                mailAutoResponders: 0,
                redirects: 0,
                databases: 0,
                traffic: 0,
            ),
            false,
        ];
        yield 'Too many databases prohibit the downgrade ' => [
            new UserStatistics(
                activeDomains: 3,
                subdomains: 1,
                diskSpaceInMb: 1024,
                mailDiskSpaceInMb: 1024,
                mailBoxes: 1,
                mailLists: 0,
                mailAutoResponders: 0,
                redirects: 0,
                databases: 3,
                traffic: 0,
            ),
            false,
        ];
    }
}
