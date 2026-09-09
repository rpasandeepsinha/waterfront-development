<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Services\Ssl;

use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Enum\StatusEnum;
use RealtimeRegister\Domain\ResendDcvCollection;
use RealtimeRegister\Exceptions\RealtimeRegisterClientException;
use RealtimeRegister\RealtimeRegister;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\RtrClient\Services\Enums\SupportedDcvType;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class CertificateRequester
{
    public function __construct(
        private readonly RealtimeRegister $rtr,
        private readonly ConfigurationInterface $configuration,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $customerData
     *
     * @return int The process ID of the requested certificate
     */
    public function request(
        array $customerData,
        string $product,
        int $period,
        string $csr
    ): int {
        Assert::stringNotEmpty($product);
        $commonName = $this->getCommonNameFromCsr($csr);
        $organization = Arr::get($customerData, 'organization');
        $department = Arr::get($customerData, 'department');
        $postalCode = Arr::get($customerData, 'address.zip_code');
        $city = Arr::get($customerData, 'address.city');
        $coc = Arr::get($customerData, 'coc_number');
        assert(is_string($organization) || is_null($organization));
        assert(is_string($department) || is_null($department));
        assert(is_string($postalCode) || is_null($postalCode));
        assert(is_string($city) || is_null($city));
        assert(is_string($coc) || is_null($coc));

        $certificateInfo = $this->rtr->certificates->requestCertificate(
            customer: $this->configuration->getAsString('realtimeregisterclient.handles.billing'),
            product: $product,
            period: $period,
            csr: $csr,
            organization: $organization,
            department: $department,
            address: self::getAddress($customerData),
            postalCode: $postalCode,
            city: $city,
            coc: $coc,
            approver: [
                'firstName' => Arr::get($customerData, 'first_name'),
                'lastName' => Arr::get($customerData, 'last_name'),
                'email' => Arr::get($customerData, 'email'),
                'voice' => self::getVoice($customerData),
            ],
            dcv: [
                [
                    'commonName' => $commonName,
                    'type' => 'DNS',
                ],
            ],
        );

        Assert::notNull($certificateInfo->processId, 'Realtime-Register did not return a processId)');
        return $certificateInfo->processId;
    }

    public function retrieve(int $certificateId): Result
    {
        Assert::positiveInteger($certificateId);

        try {
            $certificate = $this->rtr->certificates->getCertificate($certificateId);

            $result = new Result();
            $result->setStatus(Result::STATUS_OK);
            $result->setResponseData($certificate->toArray());
            $result->setCertificateId($certificate->id);
            $result->setCertificateStatus($certificate->status);
            $result->setIsCertificateActive($certificate->status === StatusEnum::STATUS_ACTIVE);
        } catch (RealtimeRegisterClientException $exception) {
            $result = new Result();
            $result->setStatus(Result::STATUS_ERROR);
            $result->setCertificateStatus('unknown');
            $result->setIsCertificateActive(false);
            $result->setErrorCode($exception->getCode());
            $result->setErrorMessage($exception->getMessage());
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $customerData
     *
     * @return int The process ID of the requested certificate
     */
    public function reissue(
        int $certificateId,
        array $customerData,
        string $csr
    ): int {
        Assert::positiveInteger($certificateId);
        $commonName = $this->getCommonNameFromCsr($csr);

        $coc = Arr::get($customerData, 'coc_number');
        $city = Arr::get($customerData, 'address.city');
        $postalCode = Arr::get($customerData, 'address.zip_code');
        $department = Arr::get($customerData, 'department');
        $organization = Arr::get($customerData, 'organization');

        assert(is_string($coc) || is_null($coc));
        assert(is_string($city) || is_null($city));
        assert(is_string($postalCode) || is_null($postalCode));
        assert(is_string($department) || is_null($department));
        assert(is_string($organization) || is_null($organization));

        $certificateInfo = $this->rtr->certificates->reissueCertificate(
            certificateId: $certificateId,
            csr: $csr,
            organization: $organization,
            department: $department,
            address: self::getAddress($customerData),
            postalCode: $postalCode,
            city: $city,
            coc: $coc,
            approver: [
                'firstName' => Arr::get($customerData, 'first_name'),
                'lastName' => Arr::get($customerData, 'last_name'),
                'email' => Arr::get($customerData, 'email'),
                'voice' => self::getVoice($customerData),
            ],
            dcv: [
                [
                    'commonName' => $commonName,
                    'type' => 'DNS',
                ],
            ],
        );

        Assert::notNull($certificateInfo->processId, 'Realtime-Register did not return a processId)');
        return $certificateInfo->processId;
    }

    /**
     * @param array<string, mixed> $customerData
     *
     * @return int The process ID of the requested certificate
     */
    public function renew(
        int $certificateId,
        array $customerData,
        int $period,
        string $csr
    ): int {
        Assert::positiveInteger($certificateId);
        $commonName = $this->getCommonNameFromCsr($csr);

        $coc = Arr::get($customerData, 'coc_number');
        $city = Arr::get($customerData, 'address.city');
        $postalCode = Arr::get($customerData, 'address.zip_code');
        $department = Arr::get($customerData, 'department');
        $organization = Arr::get($customerData, 'organization');

        assert(is_string($coc) || is_null($coc));
        assert(is_string($city) || is_null($city));
        assert(is_string($postalCode) || is_null($postalCode));
        assert(is_string($department) || is_null($department));
        assert(is_string($organization) || is_null($organization));

        $certificateInfo = $this->rtr->certificates->renewCertificate(
            certificateId: $certificateId,
            period: $period,
            csr: $csr,
            organization: $organization,
            department: $department,
            address: self::getAddress($customerData),
            postalCode: $postalCode,
            city: $city,
            coc: $coc,
            approver: [
                'firstName' => Arr::get($customerData, 'first_name'),
                'lastName' => Arr::get($customerData, 'last_name'),
                'email' => Arr::get($customerData, 'email'),
                'voice' => self::getVoice($customerData),
            ],
            dcv: [
                [
                    'commonName' => $commonName,
                    'type' => 'DNS',
                ],
            ],
        );

        Assert::notNull($certificateInfo->processId, 'Realtime-Register did not return a processId)');
        return $certificateInfo->processId;
    }

    public function resendDcv(int $processId, string $commonName): Result
    {
        Assert::positiveInteger($processId);
        Assert::stringNotEmpty($commonName);

        $dcv = ResendDcvCollection::fromArray([
            [
                'commonName' => $commonName,
                'type' => SupportedDcvType::DNS->value,
            ],
        ]);

        try {
            /** @var array{warning?: string}|null $dcvResponse */
            $dcvResponse = $this->rtr->certificates->resendDcv($processId, $dcv);

            if (is_array($dcvResponse) && array_key_exists('warning', $dcvResponse)) {
                $this->logger->error(self::class . '::resendDcv - RTR returned warning', [
                    LoggingContextKeys::META => [
                        'processId' => $processId,
                        'commonName' => $commonName,
                        'warning' => $dcvResponse['warning'],
                    ],
                ]);

                $result = new Result();
                $result->setStatus(Result::STATUS_ERROR);
                $result->setErrorCode(Response::HTTP_UNPROCESSABLE_ENTITY);
                $result->setErrorMessage('RTR returned a warning for DCV resend');
                $result->setReason($dcvResponse['warning']);
                return $result;
            }

            $result = new Result();
            $result->setStatus(Result::STATUS_OK);
            return $result;
        } catch (RealtimeRegisterClientException $exception) {
            $this->logger->error(self::class . '::resendDcv - client exception', [
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::META => [
                    'processId' => $processId,
                    'commonName' => $commonName,
                    'statusCode' => $exception->getCode(),
                    'errorMessage' => $exception->getMessage(),
                ],
            ]);

            $result = new Result();
            $result->setStatus(Result::STATUS_ERROR);
            $result->setErrorCode($exception->getCode());
            $result->setErrorMessage($exception->getMessage());
            $result->setResponseData([
                'processId' => $processId,
                'dcv' => [['commonName' => $commonName, 'type' => 'DNS']],
            ]);
            return $result;
        }
    }

    public function getCommonNameFromCsr(string $csr): string
    {
        $csrData = openssl_csr_get_subject($csr, false);

        if (! is_array($csrData)) {
            throw new RuntimeException('openssl_csr_get_subject called with invalid csr');
        }

        Assert::keyExists($csrData, 'commonName');
        Assert::stringNotEmpty($csrData['commonName']);
        return $csrData['commonName'];
    }

    /**
     * @param array<mixed> $customerData
     */
    private static function getAddress(array $customerData): string
    {
        $streetName = Arr::get($customerData, 'address.street_name', ' ');
        $streetNumber = Arr::get($customerData, 'address.street_number', ' ');

        Assert::string($streetName);
        Assert::string($streetNumber);

        return $streetName . ' ' . $streetNumber;
    }

    /**
     * Get the voice contact info == phone number.
     *
     * @param array<string, mixed> $customerData
     */
    private static function getVoice(array $customerData): string
    {
        $phoneCountryCode = Arr::get($customerData, 'phone_country_code');
        $phoneAreaCode = Arr::get($customerData, 'phone_area_code');
        $phoneSubscriberNumer = Arr::get($customerData, 'phone_subscriber_number');

        assert(is_string($phoneCountryCode) || is_null($phoneCountryCode));
        assert(is_string($phoneAreaCode) || is_null($phoneAreaCode));
        assert(is_string($phoneSubscriberNumer) || is_null($phoneSubscriberNumer));

        return sprintf(
            '+%s.%s',
            $phoneCountryCode,
            $phoneAreaCode . $phoneSubscriberNumer,
        );
    }
}
