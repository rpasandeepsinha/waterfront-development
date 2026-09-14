<?php

declare(strict_types=1);

namespace Waterfront\Infra\CaddyClient\Factories;

use Waterfront\Infra\CaddyClient\Exceptions\InvalidRedirectSchemeException;

class RedirectFrameHtmlFactory
{
    public function make(string $targetUrl): string
    {
        $scheme = strtolower((string) parse_url($targetUrl, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidRedirectSchemeException($scheme);
        }

        $escapedUrl = htmlspecialchars($targetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf(
            '<iframe src="%s" style="position:fixed; top:0; left:0; bottom:0; right:0; width:100%%; height:100%%; border:none; margin:0; padding:0; overflow:hidden; z-index:999999;"></iframe>',
            $escapedUrl,
        );
    }
}
