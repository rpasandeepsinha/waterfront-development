<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use JsonException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SensitiveParameter;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException as CloudStackClientException;

class CloudStackBaseClient
{
    public function __construct(
        private readonly string $url,
        #[SensitiveParameter]
        private readonly string $apiKey,
        #[SensitiveParameter]
        private readonly string $secretKey,
        private readonly ClientInterface $client
    ) {
    }

    /**
     * @param array<mixed> $params
     *
     * @throws CloudStackClientException
     *
     * @return array<mixed>
     */
    public function execute(string $command, array $params = []): array
    {
        $queryString = $this->signQueryString($this->createQueryString($command, $params));

        try {
            $request = new Request('GET', $this->url . '?' . $queryString);
            $response = $this->client->send($request);
        } catch (ServerException|ClientException $e) {
            throw new CloudStackClientException(
                $e->getRequest()->getUri() . $e->getResponse()->getBody()->getContents(),
                $e->getCode(),
                $e
            );
        } catch (GuzzleException $e) {
            throw new CloudStackClientException((string) $e, $e->getCode(), $e);
        }

        return $this->parseResponse($request, $response, $command);
    }

    /**
     * @throws CloudStackClientException
     *
     * @return array<mixed>
     */
    private function parseResponse(RequestInterface $request, ResponseInterface $response, string $command): array
    {
        $rawData = $response->getBody()->getContents();

        if (! json_validate($rawData)) {
            throw new CloudStackClientException(
                sprintf(
                    'Invalid response content type for uri %s : %s',
                    $request->getUri(),
                    $rawData
                )
            );
        }

        try {
            $data = json_decode($rawData, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $data = null;
        }
        if ($data === null) {
            throw new CloudStackClientException(
                sprintf(
                    'Cannot parse json response for uri %s : %s',
                    $request->getUri(),
                    $rawData
                )
            );
        }

        /*
         * For some reason CloudStack doesn't adhere to their own
         * standard. This means that for some commands a string is returned that cannot be converted by default.
         * That is why we are now introducing this method so that we can prevent errors in those specific cases.
         */
        $responsekey = $this->convertCommandToRealResponseKey($command);

        if (! is_array($data) || ! array_key_exists($responsekey, $data)) {
            throw new CloudStackClientException(
                sprintf(
                    'Invalid response data for uri %s : %s',
                    $request->getUri(),
                    $rawData
                )
            );
        }

        return $data[$responsekey];
    }

    private function convertCommandToRealResponseKey(string $command): string
    {
        return match ($command) {
            'restoreVirtualMachine' => 'restorevmresponse',
            'resetSSHKeyForVirtualMachine' => 'resetSSHKeyforvirtualmachineresponse',
            default => strtolower($command . 'response'),
        };
    }

    /**
     * @param array<mixed> $params
     */
    private function createQueryString(string $command, array $params): string
    {
        $params['command'] = $command;
        $params['apikey'] = $this->apiKey;
        $params['response'] = 'json';

        ksort($params);
        return implode(
            '&',
            $this->mapParametersToQueryValues($params)
        );
    }

    /**
     * Recursively map parameters to query string values
     * supporting nested arrays from the given params,
     * mainly used for mapping tags in the query.
     *
     * @see https://docs.cloudstack.apache.org/en/latest/adminguide/management.html#using-tags-to-organize-resources-in-the-cloud
     *
     * @param array<string, mixed> $params
     *
     * @return array<string>
     *
     */
    private function mapParametersToQueryValues(array $params, string $prefix = ''): array
    {
        $queryParts = [];

        foreach ($params as $key => $value) {
            $encodedKey = $prefix === '' ? $key : "{$prefix}[{$key}]";

            if (is_array($value)) {
                if (array_is_list($value)) {
                    foreach ($value as $i => $item) {
                        // Handle array of objects like tags[0].key
                        if (is_array($item)) {
                            /** @var bool|float|int|resource|string|null $subValue */
                            foreach ($item as $subKey => $subValue) {
                                $queryParts[] = sprintf(
                                    '%s[%d].%s=%s',  // will end up like tags[0].key=template_slug or tags[1].value=Almalinux-9
                                    $encodedKey,
                                    $i,
                                    $subKey,
                                    rawurlencode(strval($subValue))
                                );
                            }
                            continue;
                        }

                        /** @var bool|float|int|resource|string|null $item */
                        $queryParts[] = sprintf(
                            '%s[%d]=%s',
                            $encodedKey,
                            $i,
                            rawurlencode(strval($item))
                        );
                    }
                } else {
                    /** @var array<string, mixed> $value */
                    $queryParts = array_merge(
                        $queryParts,
                        $this->mapParametersToQueryValues($value, $encodedKey)
                    );
                }
            } else {
                /** @var bool|float|int|resource|string|null $value */
                $queryParts[] = $encodedKey . '=' . rawurlencode(strval($value));
            }
        }

        return $queryParts;
    }

    private function signQueryString(string $queryString): string
    {
        $result = hash_hmac('SHA1', strtolower($queryString), $this->secretKey, true);
        return $queryString . '&signature=' . rawurlencode(base64_encode($result));
    }
}
