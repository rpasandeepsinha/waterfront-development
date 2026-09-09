<?php

declare(strict_types=1);

namespace Waterfront\Domain\Mailer;

use Waterfront\Domain\Email\Models\Template;

class TemplateRepository
{
    public function getBySlug(string $slug): Template
    {
        return Template::where('slug', $slug)->firstOrFail();
    }
}
