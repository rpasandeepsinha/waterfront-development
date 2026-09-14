<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\EventListener;

use Illuminate\Support\Facades\Log;
use SandwaveIo\Office365\Entity\Error;
use SandwaveIo\Office365\Library\Observer\Error\ErrorObserverInterface;

class CallbackErrorListener implements ErrorObserverInterface
{
    public function execute(Error $error): void
    {
        Log::error(
            sprintf(
                '%s::execute -> KPN Microsoft webhook error with messages: %s',
                self::class,
                json_encode($error->getMessages(), JSON_THROW_ON_ERROR),
            ),
        );
    }
}
