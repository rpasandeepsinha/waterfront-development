<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Waterfront\Domain\Customers\Models\Customer;

/**
 * @extends Builder<Customer>
 */
class CustomerQueryBuilder extends Builder
{
    public function search(string $value): self
    {
        if (is_numeric($value) && strlen($value) > 5 && strlen($value) <= 10) {
            $this->orWhere('customer_number', (int) $value);
        }

        $this->orWhere('email', 'ILIKE', "%$value%");
        $this->orWhere('organization', 'ILIKE', "%$value%");

        $this->orWhere(static function (Builder $builder) use ($value) {
            $keywords = explode(' ', $value);

            if (count($keywords) < 2) {
                $builder->where('first_name', 'ILIKE', "%$value%")->orWhere('last_name', 'ILIKE', "%$value%");
            } else {
                foreach ($keywords as $keyword) {
                    $builder->where(static function (Builder $builder) use ($keyword) {
                        $builder->where('first_name', 'ILIKE', "%$keyword%")->orWhere(
                            'last_name',
                            'ILIKE',
                            "%$keyword%",
                        );
                    });
                }
            }
        });

        return $this;
    }
}
