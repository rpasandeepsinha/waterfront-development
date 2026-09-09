<?php

declare(strict_types=1);

namespace Waterfront\Infra\CaddyClient\Enums;

enum RedirectType
{
    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/301
     */
    case MOVED_PERMANENTLY;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/302
     */
    case FOUND;

    /**
     * @see @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/303
     */
    case SEE_OTHER;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/307
     */
    case TEMPORARY_REDIRECT;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/308
     */
    case PERMANENT_REDIRECT;

    /**
     * This will create a simple html page with a full-size
     * frame to the destination, keeping the original URL.
     */
    case FRAME;
}
