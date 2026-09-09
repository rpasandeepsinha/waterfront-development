<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\Response;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Apps\API\Compass\Requests\ShopConfigWriteRequest;
use Waterfront\Infra\Authentication\Attributes\RequirePermission;

class ShopConfigController
{
    private const string FILE_NAME = 'shop-config.json';

    private readonly Filesystem $filesystem;

    public function __construct(
        FilesystemManager $filesystemManager,
    ) {
        $this->filesystem = $filesystemManager->disk('uiconfig');
    }

    /**
     * @throws Exception
     */
    public function show(): Response
    {
        $contents = $this->filesystem->get(self::FILE_NAME);
        if ($contents === null || $contents === '') {
            throw new Exception('Failed to read shop config');
        }
        return new Response($contents)
            ->header('Content-Type', 'application/json');
    }

    #[RequirePermission(Permissions::MANAGE_SHOP_CONFIG, SchemaId::EMPLOYEE)]
    public function update(ShopConfigWriteRequest $request): Response
    {
        $data = $request->post();
        $data['experiments'] = (object) $data['experiments'];
        $contents = json_encode($data);
        assert($contents !== false);
        if (! $this->filesystem->put(self::FILE_NAME, $contents)) {
            throw new Exception('Failed to write shop config');
        }
        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
