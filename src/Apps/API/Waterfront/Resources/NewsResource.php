<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Infra\News\DTO\News;

/**
 * @property News $resource
 *
 * @mixin News
 */
class NewsResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, int|string|null|array<string, string|null>>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'author' => $this->author,
            'date' => $this->date,
            'url' => $this->url,
            'cover' => [
                'thumbnail' => $this->cover->thumbnail,
                'small' => $this->cover->small,
                'medium' => $this->cover->medium,
                'large' => $this->cover->large,
            ],
        ];
    }
}
