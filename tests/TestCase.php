<?php

declare(strict_types=1);

namespace Filagora\AiForums\Tests;

use Filagora\Ai\AiServiceProvider;
use Filagora\AiForums\AiForumsServiceProvider;
use Filagora\AiForums\Tests\Support\HostAccessServiceProvider;
use Filagora\AiForums\Tests\Support\TestSupportServiceProvider;
use Filagora\AiForums\Tests\Support\TestUser;
use Filagora\Core\CoreServiceProvider;
use Filagora\Forums\ForumsServiceProvider;
use Filagora\Search\SearchServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            CoreServiceProvider::class,
            ForumsServiceProvider::class,
            SearchServiceProvider::class,
            AiServiceProvider::class,
            AiForumsServiceProvider::class,
            TestSupportServiceProvider::class,
            HostAccessServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('filagora.user_model', TestUser::class);
        $app['config']->set('auth.providers.users.model', TestUser::class);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }
}
