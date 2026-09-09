<?php

declare(strict_types=1);

namespace Waterfront\Support\Http\Controllers\Api\V1;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Payments\Exceptions\PaymentException;
use Waterfront\Domain\Payments\Services\CustomerSharedPaymentService;

class PaymentController
{
    public function __construct(
        private readonly Redirector $redirector,
        private readonly CustomerSharedPaymentService $paymentService,
    ) {
    }

    /**
     * Webhook request handler.
     *
     * This webhook is called every time the status of a payment changes. However, we only get the id of the payment,
     * so we have to check the new status ourselves.
     *
     * @throws PaymentException
     *
     * @return Response Always an empty 200, even if something goes wrong:
     *                  https://docs.mollie.com/guides/webhooks#how-to-handle-unknown-ids
     */
    public function webhook(Request $request, CustomerSharedPaymentService $paymentService): Response
    {
        $paymentService->attemptCustomerApproval(strval($request->string('id')));

        return new Response(null, 200);
    }

    /**
     * Redirect url after mollie.
     * Go to the confirmation page if the payment status is paid.
     * Go to the order overview page if the payment status isnt paid.
     */
    public function redirect(Order $order): RedirectResponse
    {
        $redirectUrl = $order->isPaid() ? $this->paymentService->getConfirmationUrl() : $this->paymentService->getRedirectUrl();

        // Safely add the 'order' query parameter to the URL.
        $redirectUrl = Request::create($redirectUrl)->fullUrlWithQuery(['order' => $order->id]);

        return $this->redirector->to($redirectUrl);
    }
}
