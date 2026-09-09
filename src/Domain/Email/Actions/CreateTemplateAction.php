<?php

declare(strict_types=1);

namespace Waterfront\Domain\Email\Actions;

use Waterfront\Domain\Email\Models\Template;

// Empty strings are not used anymore because they are setup in hubspot. They will later be removed.
class CreateTemplateAction
{
    public function execute(
        string $slug,
        ?string $hubspotTemplateId,
    ): void {
        $template = new Template();
        $template->title = '';
        $template->subject = '';
        $template->header = '';
        $template->body = '';
        $template->footer = '';
        $template->hubspot_template_id = $hubspotTemplateId;
        $template->slug = $slug;
        $template->save();
    }
}
