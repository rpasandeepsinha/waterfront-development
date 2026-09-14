<?php

declare(strict_types=1);

namespace Waterfront\Infra\Authentication\Listeners;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\App;
use Waterfront\Infra\Authentication\AuthenticationManager;

class ResetConsoleAuthenticationListener
{
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
    ) {
    }

    public function handle(JobProcessing $jobProcessing): void
    {
        // Checking  if an authenticated subject is already set is necessary  so we don't overwrite it.
        // Extra safety check to see if we're in CLI context
        if (App::runningInConsole()) {
            try {
                $this->authenticationManager->getAuthenticatedSubject();
            } catch (AuthenticationException) {
                $this->authenticationManager->handleConsole();
            }
        }
    }
}
