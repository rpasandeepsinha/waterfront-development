<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Transfer;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Waterfront\Domain\Transfers\Rules\TransferSubscription;
use Waterfront\Domain\Transfers\Services\TransferService;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Translation\TranslatorInterface;

class StoreRequest extends FormRequest
{
    /**
     * @throws AuthenticationException
     *
     * @return array<mixed>
     */
    public function rules(): array
    {
        $transferService = $this->container->make(TransferService::class);
        $translator = $this->container->make(TranslatorInterface::class);
        /** @var AuthenticationManager $authManager */
        $authManager = $this->container->make(AuthenticationManager::class);

        $receiver = $this->request->all('receiver');
        $receiverEmail = array_key_exists('email', $receiver) ? $receiver['email'] : '';

        $customer = $authManager->getAuthenticatedCustomer()->customer;

        return [
            'subscriptions'            => ['required', 'array'],
            'subscriptions.*.uuid'     => ['required_with:subscriptions', new TransferSubscription($transferService, $translator, $customer)],
            'receiver.customer_number' => [
                'required',
                'integer',
                Rule::exists(
                    'customers',
                    'customer_number',
                )->where(
                    'email',
                    $receiverEmail
                ),
            ],
            'receiver.email'           => ['required', 'email'],
        ];
    }
}
