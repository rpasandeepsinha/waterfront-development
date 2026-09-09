<?php

declare(strict_types=1);

namespace Waterfront\Domain\Email\Actions;

use Waterfront\Domain\Email\Models\Template;

class UpdateTemplateAction
{
    public function execute(Template $template, string $hubspotTemplateId): void
    {
        $template->hubspot_template_id = $hubspotTemplateId !== '' ? $hubspotTemplateId : null;
        $template->save();
    }
}
