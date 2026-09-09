<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\Products;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Domain\Products\ProductListUpdater;

#[AsCommand(name: 'export:products')]
#[Description('Export the products into the object storage bucket')]
class UpdateProductsS3 extends Command
{
    public function handle(ProductListUpdater $productListUpdater): int
    {
        $productListUpdater->update();

        return self::SUCCESS;
    }
}
