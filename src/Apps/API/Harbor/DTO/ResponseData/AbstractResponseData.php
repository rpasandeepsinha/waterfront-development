<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Harbor\DTO\ResponseData;

use JsonSerializable;

abstract class AbstractResponseData implements JsonSerializable
{
    /** Every *ResponseData class should implement JsonSerializable. */
}
