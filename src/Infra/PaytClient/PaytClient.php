<?php

declare(strict_types=1);

namespace Waterfront\Infra\PaytClient;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Serializer;
use Waterfront\Infra\PaytClient\DTO\PaytCredentials;
use Waterfront\Infra\PaytClient\DTO\PaytDebtorDTO;
use Waterfront\Infra\PaytClient\DTO\PaytMessageDTO;
use Waterfront\Infra\PaytClient\DTO\PaytMessagesResponseDTO;
use Waterfront\Support\Enums\LoggingContextKeys;

class PaytClient
{
    private const string ENDPOINT_V_1_DEBTORS = 'debtors';
    private const string ENDPOINT_V_1_MESSAGES = 'messages';

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly Serializer $serializer,
        private readonly PaytCredentials $credentials,
    ) {
    }

    /**
     * Fetches a single debtor by debtor number. The Payt API accepts comma-separated debtor_numbers,
     * we intentionally pass one at a time since webhook events always contain a single debtor.
     *
     *
     * @throws GuzzleException
     *
     * @return array<int, PaytDebtorDTO>
     */
    public function getDebtorByDebtorNumber(string $debtorNumber): array
    {
        $response = $this->httpClient->request('GET', self::ENDPOINT_V_1_DEBTORS, [
            'headers' => $this->getAuthorizationHeader(),
            'query' => [
                'administration_id' => $this->credentials->administrationId,
                'debtor_numbers' => $debtorNumber,
            ],
        ]);

        $body = (string) $response->getBody();

        $this->logger->debug('Payt get debtor response', [
            LoggingContextKeys::RESPONSE_CODE => $response->getStatusCode(),
            LoggingContextKeys::RESPONSE_DATA => $body,
        ]);

        $decoded = json_decode($body, true);
        assert(is_array($decoded));
        $data = $decoded['data'] ?? [];
        assert(is_array($data));

        /** @var array<int, PaytDebtorDTO> $debtors */
        $debtors = $this->serializer->denormalize($data, PaytDebtorDTO::class . '[]', 'array');

        return $debtors;
    }

    public function getLastMessageByInvoiceId(string $invoiceId): ?PaytMessageDTO
    {
        return $this->getLastMessage(['invoice_ids' => $invoiceId]);
    }

    public function getLastMessageByCreditCaseId(string $creditCaseId): ?PaytMessageDTO
    {
        return $this->getLastMessage(['credit_case_ids' => $creditCaseId]);
    }

    public function getLastMessageByDebtorId(string $debtorId): ?PaytMessageDTO
    {
        return $this->getLastMessage(['debtor_ids' => $debtorId]);
    }

    /**
     * Fetches all messages for a given invoice ID(s) (max 100 per request).
     *
     *
     * @throws GuzzleException
     *
     * @return array<int, PaytMessageDTO>
     */
    public function getMessagesByInvoiceId(string $invoiceId): array
    {
        return $this->getMessages(['invoice_ids' => $invoiceId])->data;
    }

    /**
     * Fetches all messages for the given debtor ID(s) (max 100 per request).
     *
     * @throws GuzzleException
     *
     * @return array<int, PaytMessageDTO>
     */
    public function getMessagesByDebtorId(string $debtorId): array
    {
        return $this->getMessages(['debtor_ids' => $debtorId])->data;
    }

    /**
     * Fetches all messages for the given debtor ID(s) (max 100 per request).
     *
     * @throws GuzzleException
     *
     * @return array<int, PaytMessageDTO>
     */
    public function getMessagesByCreditCaseId(string $creditCaseId): array
    {
        return $this->getMessages(['credit_case_ids' => $creditCaseId])->data;
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function getLastMessage(array $queryParams = []): ?PaytMessageDTO
    {
        $perPage = 500;
        $defaultParams = ['per_page' => $perPage];
        $previousPage = null;
        $currentPage = $this->getMessages($defaultParams + $queryParams);

        // we need to navigate the last page (with content) to retrieve the last message.
        while (sizeof($currentPage->data) >= $perPage) {
            $previousPage = $currentPage;
            $currentPage = $this->getMessages(
                ['cursor' => $previousPage->pagination?->cursor] + $defaultParams + $queryParams,
            );
        }

        if ($previousPage === null && sizeof($currentPage->data) === 0) {
            return null;
        } elseif ($previousPage !== null && sizeof($currentPage->data) === 0) {
            return array_last($previousPage->data);
        }

        return array_last($currentPage->data);
    }

    /**
     * @return string[]
     */
    private function getAuthorizationHeader(): array
    {
        return ['Authorization' => 'Bearer ' . $this->credentials->apiKey];
    }

    /**
     * Fetches all messages for a given set of query params to override the default.
     *
     * @param array<string, mixed> $queryParams
     *
     * @throws GuzzleException
     */
    private function getMessages(array $queryParams = []): PaytMessagesResponseDTO
    {
        $defaulQueryParams = [
            'administration_id' => $this->credentials->administrationId,
            'fields' => '{"only": ["sender_type", "subject", "credit_case_id", "id", "content", "sent_at", "received_at"]}',
            'per_page' => 100,
        ];
        $response = $this->httpClient->request('GET', self::ENDPOINT_V_1_MESSAGES, [
            'headers' => $this->getAuthorizationHeader(),
            'query' => $defaulQueryParams + $queryParams,
        ]);

        $body = (string) $response->getBody();

        $this->logger->debug('Payt get messages response', [
            LoggingContextKeys::RESPONSE_CODE => $response->getStatusCode(),
            LoggingContextKeys::RESPONSE_DATA => $body,
        ]);

        $decoded = json_decode($body, true);

        return $this->serializer->denormalize($decoded, PaytMessagesResponseDTO::class, 'array');
    }
}
