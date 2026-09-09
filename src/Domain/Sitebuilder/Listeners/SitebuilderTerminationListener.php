<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder\Listeners;

use Illuminate\Contracts\Bus\Dispatcher;
use Waterfront\Domain\Sitebuilder\Events\TerminateSitebuilderHosting;
use Waterfront\Domain\Sitebuilder\Jobs\TerminateSitebuilderHosting as TerminateSitebuilderHostingJob;

class SitebuilderTerminationListener
{
    public function __construct(private readonly Dispatcher $dispatcher)
    {
    }

    public function handle(TerminateSitebuilderHosting $event): void
    {
        $this->dispatcher->dispatch(new TerminateSitebuilderHostingJob($event));
    }
}
