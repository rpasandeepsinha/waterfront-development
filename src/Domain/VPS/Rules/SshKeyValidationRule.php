<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Rules;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\VPS\Repositories\SshKeyRepository;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;
use Webmozart\Assert\Assert;

class SshKeyValidationRule extends AbstractValidator
{
    private string $message;

    public function __construct(
        private readonly Customer $customer,
        private readonly TranslatorInterface $translator,
        private readonly ProductRepository $productRepository,
        private readonly ProductSpecRepository $productSpecRepository,
        private readonly SshKeyRepository $sshKeyRepository,
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        Assert::isArray($value);
        Assert::keyExists($value, 'slug');
        Assert::string($value['slug']);

        $productSlug = $value['slug'];
        $product = $this->productRepository->findProductBySlug($productSlug);
        $sshKeyIsRequired = $this->productSpecRepository->booleanSpecificationIsTrue(
            $product,
            ProductSpecName::SSH_KEY_REQUIRED,
        );

        if (array_key_exists('ssh_key_uuid', $value) && $sshKeyIsRequired === false) {
            $this->message = 'validation.vps.ssh-key-not-required';

            return false;
        }

        if (! $sshKeyIsRequired) {
            return true;
        }

        if (! array_key_exists('ssh_key_uuid', $value)) {
            $this->message = 'validation.vps.ssh-key-required';

            return false;
        }

        try {
            $this->sshKeyRepository->findByCustomerAndUuid($this->customer, $value['ssh_key_uuid']);

            return true;
        } catch (ModelNotFoundException) {
            $this->message = 'validation.vps.ssh-key-not-found';

            return false;
        }
    }

    protected function message(): string
    {
        return $this->translator->translate($this->message);
    }
}
