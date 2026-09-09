<?php

declare(strict_types=1);

namespace Waterfront\Infra\News\DTO;

class News
{
    public int $id;

    public bool $ishtml = false;

    public string $title;

    public string $description;

    public string $url;

    public ?string $language = null;

    public string $date;

    public ?string $author = null;

    public NewsCover $cover;

    public ?string $locale = null;
}
