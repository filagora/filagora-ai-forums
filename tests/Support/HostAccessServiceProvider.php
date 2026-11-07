<?php

declare(strict_types=1);

namespace Filagora\AiForums\Tests\Support;

use Filagora\Core\Contracts\AccessChecker;
use Illuminate\Support\ServiceProvider;

/**
 * The host application's own permission system, as a test double: a user may do what
 * `HostAccess::$granted` lists for them, by user id.
 */
class HostAccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AccessChecker::class, HostAccess::class);
    }
}
