<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use Spatie\ArrayToXml\ArrayToXml;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\SecretKeyCreate\Result as SecretKeyCreateResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\SecretKeyGet\Result as SecretKeyGetResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SecretKeyInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SessionTokenInterface;
use Waterfront\Domain\Ssl\Interfaces\InstallInterface;
use Waterfront\Domain\Ssl\Interfaces\Models\CertificateInstall\Parameters as InstallCertificateParameters;
use Waterfront\Domain\Ssl\Interfaces\SelectInterface;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;
use Waterfront\Infra\PleskClient\Messages\CertificateInstall\Request as InstallCertificateRequest;
use Waterfront\Infra\PleskClient\Messages\CertificateInstall\Response as InstallCertificateResponse;
use Waterfront\Infra\PleskClient\Messages\CertificateSelect\Request as SelectCertificateRequest;
use Waterfront\Infra\PleskClient\Messages\CertificateSelect\Response as SelectCertificateResponse;
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Infra\PleskClient\Messages\SessionTokenGet\Request as SessionTokenGetRequest;
use Waterfront\Infra\PleskClient\Messages\SessionTokenGet\Response as SessionTokenGetResponse;
use Waterfront\Infra\PleskClient\Traits\PleskServerTrait;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class PleskClient implements SessionTokenInterface, InstallInterface, SelectInterface, SecretKeyInterface
{
    use PleskServerTrait;

    private const string EMAIL_ACCOUNTS_URL = '/smb/email-address/list';

    public function __construct(
        protected Client $client,
        private readonly ConfigurationInterface $configuration,
        protected LoggerInterface $logger,
        ?Connection $connection = null
    ) {
        $this->setConnection($connection ?? new Connection());
    }

    /**
     * @throws PleskClientException
     * @throws JsonException
     * @throws GuzzleException
     */
    public function getSessionToken(string $username, string $ipAddress): string
    {
        $request = new SessionTokenGetRequest($username, $ipAddress);
        $httpResponse = $this->send($request);
        $response = new SessionTokenGetResponse($httpResponse);

        if ($response->getStatusCode() !== 200) {
            throw PleskClientException::noPleskClientSessionTokenFound($username, $ipAddress, $response->getStatusMessage(), $response->getStatusCode());
        }

        $this->logger->info(
            self::class . '::getSessionToken - session token retrieved',
            [
                LoggingContextKeys::RESPONSE_DATA => $response->getToken(),
            ]
        );

        if ($response->getToken() === '') {
            $this->logger->debug(
                self::class . '::getSessionToken - The gathered token was NULL',
                [
                    LoggingContextKeys::RESPONSE_CODE => $response->getStatusCode(),
                    LoggingContextKeys::RESPONSE_DATA => $response->getToken(),
                    LoggingContextKeys::META =>
                        [
                            'statusMessage' => $response->getStatusMessage(),
                            'username' => $username,
                            'ipAddress' => $ipAddress,
                        ],

                ]
            );

            throw  PleskClientException::InvalidArgumentException('The session token is empty.');
        }

        return $response->getToken();
    }

    /**
     * @throws PleskClientException
     * @throws JsonException
     * @throws GuzzleException
     */
    public function getSsoUrl(
        string $username,
        string $ipAddress,
        bool $redirectToMail = false
    ): string {
        $token = $this->getSessionToken($username, $ipAddress);

        $query = ['PHPSESSID' => $token];

        if ($redirectToMail) {
            $query['success_redirect_url'] = self::EMAIL_ACCOUNTS_URL;
        }

        return sprintf('%s/enterprise/rsession_init.php?%s', $this->server->getApiUrlAttribute(), http_build_query($query));
    }

    /**
     * @throws PleskClientException
     */
    public function getServerSsoUrl(): string
    {
        throw PleskClientException::serverSsoException();
    }

    /**
     * @throws PleskClientException
     * @throws JsonException
     * @throws GuzzleException
     */
    public function installCertificate(InstallCertificateParameters $parameters): string
    {
        $this->logger->info(
            self::class . '::installCertificate - initiate',
            [
                LoggingContextKeys::DOMAIN_NAME => $parameters->getDomain(),
            ]
        );

        $request = new InstallCertificateRequest($parameters);
        $httpResponse = $this->send($request);
        $response = new InstallCertificateResponse($httpResponse);

        if ($response->getStatusCode() !== 200) {
            throw PleskClientException::installingCertificateFailed($parameters->getDomain(), $response->getStatusCode());
        }

        $this->logger->info(
            self::class . '::installCertificate - install result',
            [
                LoggingContextKeys::DOMAIN_NAME => $parameters->getDomain(),
                LoggingContextKeys::RESPONSE_DATA => $response->getResult(),
                LoggingContextKeys::META =>
                    [
                        'errorCode' => $response->getErrorCode(),
                        'errorText' => $response->getErrorText(),
                    ],
            ]
        );

        return $response->getResult();
    }

    /**
     * @throws PleskClientException
     * @throws JsonException
     * @throws GuzzleException
     */
    public function selectCertificate(string $domain, string $certificateName): string
    {
        $this->logger->info(
            self::class . '::selectCertificate - initiate',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
            ]
        );

        $request = new SelectCertificateRequest($domain, $certificateName);
        $httpResponse = $this->send($request);
        $response = new SelectCertificateResponse($httpResponse);

        if ($response->getStatusCode() !== 200) {
            throw  PleskClientException::selectCertificateFailed($domain, $response->getStatusCode());
        }

        $this->logger->info(
            self::class . '::selectCertificate - select result',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::RESPONSE_DATA => $response->getResult(),
                LoggingContextKeys::META =>
                    [
                        'errorCode' => $response->getErrorCode(),
                        'errorText' => $response->getErrorText(),
                    ],
            ]
        );

        return $response->getResult();
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function getSecretKeys(): SecretKeyGetResult
    {
        $message = [
            'secret_key' => [
                'get_info' => [
                    'filter' => [],
                ],
            ],
        ];

        $xmlMessage = ArrayToXml::convert($message, 'packet', false, 'UTF-8', options: ['convertNullToXsiNil' => true]);

        $httpResponse = $this->sendSecretKeysRequest('GET', $xmlMessage);

        $xmlResponse = new SimpleXMLElement((string) $httpResponse->getBody());

        $results = $xmlResponse->system;
        if ($results->count() > 0) {
            $status = (string) $results->status;
        } else {
            $results = $xmlResponse->secret_key->get_info;
            $status = (string) $results->result[0]?->status;
        }

        if ($status === SecretKeyGetResult::STATUS_ERROR) {
            return SecretKeyGetResult::create(
                [
                    'status' => $status,
                    'errorCode' => (string) $results->errcode,
                    'errorMessage' => (string) $results->errtext,
                ]
            );
        }

        $secretKeys = [];
        foreach ($results->result as $result) {
            // We only want the secret key for the administrator
            if ((string) $result->key_info->login !== $this->connection->getUsername()) {
                continue;
            }

            $secretKeys[(string) $result->key_info->ip_address] = (string) $result->key_info->key;
        }

        return SecretKeyGetResult::create(
            [
                'status' => $status,
                'secretKeys' => $secretKeys,
            ]
        );
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function createSecretKey(string $ipAddress): SecretKeyCreateResult
    {
        $message = [
            'secret_key' => [
                'create' => [
                    'ip_address' => $ipAddress,
                ],
            ],
        ];

        $xmlMessage = ArrayToXml::convert($message, 'packet', false, 'UTF-8', options: ['convertNullToXsiNil' => true]);

        $httpResponse = $this->sendSecretKeysRequest('POST', $xmlMessage);

        $xmlResponse = new SimpleXMLElement((string) $httpResponse->getBody());

        $result = $xmlResponse->system;
        if ($result->count() === 0) {
            $result = $xmlResponse->secret_key->create->result;
        }

        $status = (string) $result->status;

        if ($status === SecretKeyCreateResult::STATUS_OK) {
            return SecretKeyCreateResult::create(
                [
                    'status' => $status,
                    'secret_key' => (string) $result->key,
                ]
            );
        }

        return SecretKeyCreateResult::create(
            [
                'status' => $status,
                'error_code' => (string) $result->errcode,
                'error_message' => (string) $result->errtext,
            ]
        );
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    protected function send(RequestInterface $request): ResponseInterface
    {
        $headers = [
            'HTTP_AUTH_LOGIN' => $this->connection->getUsername(),
            'HTTP_AUTH_PASSWD' => $this->connection->getPassword(),
        ];

        if ($this->connection->getSecretKey() !== null) {
            $headers = [
                'KEY' => $this->connection->getSecretKey(),
                'Content-Type' => 'application/xml',
            ];
        }

        $logMessage = sprintf('Sending Plesk request: %s', $this->getXml($request, logOutput: true));

        $this->logger->debug($logMessage);

        Assert::notNull($this->connection->getApiUrl());

        return $this->client->request('POST', $this->connection->getApiUrl() . '/enterprise/control/agent.php', [
            'verify' =>  $this->configuration->getAsBoolean('hosting-service-client.connection.verify_ssl'),
            'headers' => $headers,
            'body' => $this->getXml($request),
            'http_errors' => false,
            'timeout' => 120,
            'connect_timeout' => 5,
            'debug' => $this->configuration->getAsBoolean('hosting-service-client.connection.debug_mode'),
        ]);
    }

    /**
     * @throws GuzzleException
     */
    private function sendSecretKeysRequest(string $verb, string $xmlMessage): ResponseInterface
    {
        return $this->client->request(
            $verb,
            $this->connection->getApiUrl() . '/enterprise/control/agent.php',
            [
                'headers' => [
                    'HTTP_AUTH_LOGIN' => $this->connection->getUsername(),
                    'HTTP_AUTH_PASSWD' => $this->connection->getPassword(),
                ],
                'body' => $xmlMessage,
                'http_errors' => false,
            ]
        );
    }

    private function getXml(RequestInterface $request, bool $logOutput = false): string
    {
        if (property_exists($request, 'maskSecrets')) {
            $request->maskSecrets = $logOutput;
        }

        return ArrayToXml::convert($request->getMessage(), 'packet', false, 'UTF-8', options: ['convertNullToXsiNil' => true]);
    }
}
