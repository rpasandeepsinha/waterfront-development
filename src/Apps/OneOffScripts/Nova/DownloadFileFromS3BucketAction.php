<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Nova;

use Carbon\CarbonImmutable;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\OneOffScripts\NovaOneOffScriptAbstractAction;

class DownloadFileFromS3BucketAction extends NovaOneOffScriptAbstractAction
{
    public const string SLUG = 'download-file-from-s3-bucket';

    /**
     * @return array<Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ...$this->getOneOffScriptInfoFields(),
        ];
    }

    public function handle(): ActionResponse|static
    {
        /** @var string $disk */
        $disk = Env::get('S3_PRODUCTS_BUCKET', 'products');
        $tmpUrl = Storage::disk($disk)
            ->temporaryUrl(
                'tldinfo.json',
                CarbonImmutable::now()->addMinutes(5)
            );

        return ActionResponse::download('tldinfo.json', $tmpUrl);
    }

    public function isExecuted(): bool
    {
        return $this->oneOffScript->isExecuted();
    }

    protected function getOneOffScriptSlug(): string
    {
        return self::SLUG;
    }

    protected function getOneOffScriptTicketUrl(): string
    {
        return 'https://yh-jira.atlassian.net/browse/SWD-6648';
    }
}
