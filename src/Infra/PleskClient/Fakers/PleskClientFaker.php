<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Fakers;

use GuzzleHttp\Psr7\Response as HttpResponse;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\SecretKeyCreate\Result as SecretKeyCreateResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\SecretKeyGet\Result as SecretKeyGetResult;
use Waterfront\Domain\Ssl\Interfaces\Models\CertificateInstall\Parameters as InstallCertificateParameters;
use Waterfront\Infra\PleskClient\Messages\CertificateInstall\Response as InstallCertificateResponse;
use Waterfront\Infra\PleskClient\Messages\CertificateSelect\Response as SelectCertificateResponse;
use Waterfront\Infra\PleskClient\Messages\SessionTokenGet\Response as SessionTokenGetResponse;
use Waterfront\Infra\PleskClient\PleskClient;
use Waterfront\Infra\PleskClient\Traits\DesiredResponseCodeTrait;

class PleskClientFaker extends PleskClient
{
    use DesiredResponseCodeTrait;

    public function getSessionToken(string $username, string $ipAddress): string
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = 200;
        }

        $xmlMessage = file_get_contents(__DIR__ . '/data/plesk_session_token_get_response.xml');

        /** @var string $xmlMessage */
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new SessionTokenGetResponse($httpResponse)->getToken();
    }

    public function getSecretKeys(): SecretKeyGetResult
    {
        return SecretKeyGetResult::create(
            [
                'status'     => SecretKeyGetResult::STATUS_OK,
                'secretKeys' => [
                    '1.2.3.4' => 'example_key',
                ],
            ]
        );
    }

    public function createSecretKey(string $ipAddress): SecretKeyCreateResult
    {
        return SecretKeyCreateResult::create(
            [
                'status'     => SecretKeyCreateResult::STATUS_OK,
                'secret_key' => 'example_key',
            ]
        );
    }

    public function changeServicePlan(string $domain, string $servicePlanGuuid): Result
    {
        return Result::create([
            'status' => Result::STATUS_OK,
        ]);
    }

    public function installCertificate(InstallCertificateParameters $parameters): string
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = 200;
        }

        $xmlMessage = file_get_contents(__DIR__ . '/data/plesk_certificate_install_response.xml');
        /** @var string $xmlMessage */
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new InstallCertificateResponse($httpResponse)->getResult();
    }

    public function selectCertificate(string $domain, string $certificateName): string
    {
        $desiredResponseCode = $this->getDesiredResponseCode();
        if ($desiredResponseCode === 0) {
            $desiredResponseCode = 200;
        }

        $xmlMessage = file_get_contents(__DIR__ . '/data/plesk_certificate_select_response.xml');
        /** @var string $xmlMessage */
        $httpResponse = new HttpResponse($desiredResponseCode, [], $xmlMessage);

        return new SelectCertificateResponse($httpResponse)->getResult();
    }
}
