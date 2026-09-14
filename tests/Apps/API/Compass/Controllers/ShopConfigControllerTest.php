<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\ShopConfigController;

#[CoversClass(ShopConfigController::class)]
class ShopConfigControllerTest extends IntegrationTestCase
{
    private Filesystem&MockObject $filesystem;

    /** @var array<mixed> */
    private array $validConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = $this->createMock(Filesystem::class);

        $fileSystemManager = self::createMock(FilesystemManager::class);
        $this->app->instance(FilesystemManager::class, $fileSystemManager);
        $fileSystemManager->expects(self::once())->method('disk')->with('uiconfig')->willReturn($this->filesystem);

        $validConfigEncoded = file_get_contents(__DIR__
        . '/../../../../../database/seeds/Platform/Data/shop-config.json');
        self::assertNotFalse($validConfigEncoded);
        /** @var array<mixed> $validConfig */
        $validConfig = json_decode($validConfigEncoded, true);
        $this->validConfig = $validConfig;

        new ProductFactory()->redirect()->create();
        new ProductFactory()->for(new ProductGroupFactory()->hosting())->createMany([
            ['slug' => 'local-web-basic'],
            ['slug' => 'local-webonly-basic'],
            ['slug' => 'local-webonly-grow'],
            ['slug' => 'local-webonly-start'],
            ['slug' => 'local-webonly-plus'],
            ['slug' => 'local-mailonly-basic'],
            ['slug' => 'local-mailonly-grow'],
            ['slug' => 'local-mailonly-start'],
            ['slug' => 'local-mailonly-plus'],
        ]);
        new ProductFactory()->for(new ProductGroupFactory()->dns())->createMany([
            ['slug' => 'free-dns'],
            ['slug' => 'premium-dns'],
        ]);
        new ProductFactory()->for(new ProductGroupFactory()->microsoft365())->createOne([
            'slug' => 'microsoft-business-basic',
        ]);
        new ProductFactory()->for(new ProductGroupFactory()->ssl())->createMany([
            ['slug' => 'Domain Validation SSL'],
            ['slug' => 'ssl_extended_validation'],
            ['slug' => 'ssl_wildcard'],
        ]);
    }

    #[Test]
    public function show(): void
    {
        $this->filesystem
            ->expects(self::once())
            ->method('get')
            ->with('shop-config.json')
            ->willReturn('{"lorem":"ipsum"}');

        $response = $this->actingAsEmployee()->getJson(route('admin.shop-config.show'));
        $response->assertOk();
        $response->assertExactJson([
            'lorem' => 'ipsum',
        ]);
    }

    #[Test]
    public function showFileNotExists(): void
    {
        $this->filesystem->expects(self::once())->method('get')->with('shop-config.json')->willReturn(null);

        $response = $this->actingAsEmployee()->getJson(route('admin.shop-config.show'));
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        $response->assertJsonFragment(['message' => 'Failed to read shop config']);
    }

    #[Test]
    public function showFileEmpty(): void
    {
        $this->filesystem->expects(self::once())->method('get')->with('shop-config.json')->willReturn('');

        $response = $this->actingAsEmployee()->getJson(route('admin.shop-config.show'));
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        $response->assertJsonFragment(['message' => 'Failed to read shop config']);
    }

    #[Test]
    public function update(): void
    {
        $expectedConfig = $this->validConfig;
        $expectedConfig['experiments'] = (object) ($expectedConfig['experiments'] ?? []);
        $this->filesystem
            ->expects(self::once())
            ->method('put')
            ->with('shop-config.json', json_encode($expectedConfig))
            ->willReturn(true);

        $response = $this->actingAsEmployee()->put(route('admin.shop-config.show'), $this->validConfig);
        $response->assertStatus(Response::HTTP_NO_CONTENT);
    }

    #[Test]
    public function updateFailed(): void
    {
        $expectedConfig = $this->validConfig;
        $expectedConfig['experiments'] = (object) ($expectedConfig['experiments'] ?? []);
        $this->filesystem
            ->expects(self::once())
            ->method('put')
            ->with('shop-config.json', json_encode($expectedConfig))
            ->willReturn(false);

        $response = $this->actingAsEmployee()->put(route('admin.shop-config.show'), $this->validConfig);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        $response->assertJsonFragment(['message' => 'Failed to write shop config']);
    }
}
