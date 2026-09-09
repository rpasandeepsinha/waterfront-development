<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Waterfront\Apps\API\Waterfront\Controllers\SecurityBundleController;

Route::post('security-bundle/redeem', [SecurityBundleController::class, 'redeem'])->name('security-bundle.redeem');
