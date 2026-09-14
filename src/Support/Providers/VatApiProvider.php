<?php

declare(strict_types=1);

namespace Waterfront\Support\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Env;
use SandwaveIo\Vat\Vat;
use Waterfront\Support\Providers\VatApiFakers\VatNumberApiFaker;
use Waterfront\Support\Providers\VatApiFakers\VatRateApiFaker;

class VatApiProvider extends BaseProvider implements DeferrableProvider
{
    public function register(): void
    {
        // Register the main class to use with the facade
        $useFakeClient = Env::get('APP_FAKE_VAT_API_CLIENT');
        if ($useFakeClient === 'true' || $useFakeClient === true) {
            $this->app->singleton(
                Vat::class,
                fn (): Vat => new Vat(
                    null,
                    $this->resolve(VatRateApiFaker::class),
                    $this->resolve(VatNumberApiFaker::class),
                ),
            );
        }
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            Vat::class,
        ];
    }
}
