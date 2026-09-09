<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Waterfront\Domain\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Provision\Hosting\Exceptions\DriverNotDefinedException;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;

class ValidServerRule implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    public function __construct(
        private readonly HostingServiceFactory $hostingServiceFactory,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $server = new Server();
        $server->setRawAttributes($this->data, true);

        if ($server->type === ServerType::SITEBUILDER) {
            // Sitebuilder services are not based on HostingServiceInterface so we can't call it below,
            // that's why we just return so Nova doesn't break.
            // TODO https://yh-jira.atlassian.net/browse/SWD-7549
            return;
        }

        try {
            $driverName = $this->hostingServiceFactory->getDriverFromServer($server);

            $validServer = $this->hostingServiceFactory
                ->driver($driverName)
                ->serverIsValid($server);
        } catch (DriverNotDefinedException) {
            $fail('validation.server.driver');
            return;
        }

        if (! $validServer) {
            $fail('validation.server.could-not-connect');
        }
    }

    /**
     * @phpstan-ignore-next-line No params- or return types because of laravel internal interface
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }
}
