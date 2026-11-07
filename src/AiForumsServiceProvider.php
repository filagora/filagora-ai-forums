<?php

declare(strict_types=1);

namespace Filagora\AiForums;

use Filagora\Ai\Retrieval\RetrievalSources;
use Filagora\AiForums\Console\ThreadVectorsBackfillCommand;
use Filagora\AiForums\Console\ThreadVectorsStatusCommand;
use Filagora\AiForums\Retrieval\ThreadsRetrievalSource;
use Filagora\AiForums\Search\ThreadVectorIndex;
use Filagora\AiForums\Search\ThreadVectorSync;
use Filagora\Core\Modules\Module;
use Filagora\Core\Modules\ModuleRegistry;
use Filagora\Core\Support\GateAbilities;
use Filagora\Entitlements\Registry\Definition;
use Filagora\Entitlements\Registry\EntitlementRegistry;
use Filagora\Forums\Support\Models;
use Filagora\Search\Support\SearchIndexRegistry;
use Illuminate\Support\ServiceProvider;

class AiForumsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filagora-ai-forums.php', 'filagora-ai-forums');

        $this->app->singleton(ThreadVectorSync::class);
    }

    public function boot(): void
    {
        $this->app->make(ModuleRegistry::class)->register(
            new Module('ai-forums', 'Filagora AI for forums', 'filagora/filagora-ai-forums', requires: ['core', 'forums', 'ai']),
        );

        GateAbilities::define('filagora-ai-forums.gates');
        $this->declareEntitlements();

        $this->app->make(SearchIndexRegistry::class)->register(new ThreadVectorIndex);
        $this->app->make(RetrievalSources::class)->register($this->app->make(ThreadsRetrievalSource::class), 300);
        $this->observeThreadsAndPosts();

        if ($this->app->runningInConsole()) {
            $this->commands([ThreadVectorsBackfillCommand::class, ThreadVectorsStatusCommand::class]);

            $this->publishes([
                __DIR__.'/../config/filagora-ai-forums.php' => config_path('filagora-ai-forums.php'),
            ], 'filagora-ai-forums-config');
        }
    }

    /**
     * Threads and their opening posts keep their vector current. The models are the host's (a profile
     * may swap in subclasses), so they are looked up rather than named.
     */
    private function observeThreadsAndPosts(): void
    {
        $sync = $this->app->make(ThreadVectorSync::class);
        $thread = Models::class('thread');
        $post = Models::class('post');

        $thread::created(fn ($model) => $sync->threadCreated($model));
        $thread::saved(fn ($model) => $sync->threadSaved($model));
        $thread::deleted(fn ($model) => $sync->threadDeleted($model));
        $post::saved(fn ($model) => $sync->postSaved($model));
        $post::deleted(fn ($model) => $sync->postDeleted($model));
    }

    /**
     * With `filagora-entitlements` installed, the key the ability maps to is declared so
     * `filagora:entitlements:sync` creates its definition.
     */
    private function declareEntitlements(): void
    {
        if (! class_exists(EntitlementRegistry::class) || ! $this->app->bound(EntitlementRegistry::class)) {
            return;
        }

        $registry = $this->app->make(EntitlementRegistry::class);

        foreach (GateAbilities::keys('filagora-ai-forums.gates') as $key) {
            $registry->define(new Definition($key, wildcardChildren: false));
        }
    }
}
