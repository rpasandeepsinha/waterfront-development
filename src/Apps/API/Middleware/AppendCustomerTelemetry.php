<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Sentry\State\Scope;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Support\Enums\LoggingContextKeys;
use function Sentry\configureScope as configureSentryScope;

class AppendCustomerTelemetry
{
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
    ) {
    }

    /** @param Closure(Request): (Response|RedirectResponse) $next */
    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $customer = $this->authenticationManager->getAuthenticatedCustomer();
        } catch (AuthenticationException) {
            return $next($request);
        }

        // UUID might be a deserialized instance of Ramsey\Uuid\Lazy\LazyUuidFromString
        $customerUuid = strval($customer->customer->uuid);
        $customerNumber = $customer->customer->customer_number;

        Log::withContext([
            LoggingContextKeys::CUSTOMER_NUMBER => $customerNumber,
        ]);

        configureSentryScope(function (Scope $scope) use ($customerUuid): void {
            $scope->setUser(['id' => $customerUuid]);
        });

        return $next($request);
    }
}
