<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\DirectAdminClient\Commands\Ssl\DisableLetsEncryptAutoRenew;

#[CoversClass(DisableLetsEncryptAutoRenew::class)]
class DisableLetsEncryptAutoRenewTest extends TestCase
{
    #[Test]
    public function disableLetsEncryptAutoRenewRequest(): void
    {
        $disableRequest = new DisableLetsEncryptAutoRenew();
        $disableRequest->setDomain('testdisable-le.nl');

        Assert::assertSame('POST', $disableRequest->getMethod());
        Assert::assertSame('CMD_API_SSL', $disableRequest->getCommand());

        $actualApiRequestBody = $disableRequest->getRequest()
            ->getBody()
            ->getContents();

        parse_str($actualApiRequestBody, $parsedBody);
        Assert::assertEqualsCanonicalizing([
            'action' => 'save',
            'domain' => 'testdisable-le.nl',
            'disable_letsencrypt_autorenew' => 'yes',
        ], $parsedBody);
    }
}
