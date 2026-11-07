<?php

declare(strict_types=1);

namespace Filagora\AiForums\Console;

use Filagora\Ai\Support\Embeddings;
use Filagora\AiForums\Search\ThreadVectorIndex;
use Filagora\Search\Support\SearchEngines;
use Illuminate\Console\Command;

class ThreadVectorsStatusCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'thread-vectors:status';

    /**
     * @var string
     */
    protected $description = 'Show where the thread_vectors index stands and what to run next. Read-only.';

    public function handle(SearchEngines $engines): int
    {
        $index = new ThreadVectorIndex;
        $engine = $engines->engine();
        $definition = $index->definition();
        $stats = Embeddings::dimensions() === null ? null : $engine->stats($definition);
        $expected = $index->expectedCount();

        $this->table(['', ''], [
            ['Embeddings', Embeddings::enabled() ? 'on' : 'off'],
            ['Thread vectors live syncing and search', ThreadVectorIndex::enabled() ? 'on' : 'off'],
            ['Search engine', $engine->name()],
            ['Threads that should have a vector', number_format($expected)],
            ['Documents in the index', $stats === null ? 'index missing or not answering' : number_format($stats->documents)],
        ]);

        if (! Embeddings::enabled()) {
            $this->warn('Embeddings are off (AI_EMBEDDINGS_ENABLED). Without them the index has no embedder and no vectors can be stored.');
        } elseif ($stats === null) {
            $this->line('Next: php artisan filagora:search:sync-settings --index=thread_vectors');
        } elseif ($stats->documents < $expected) {
            $this->line('Next: php artisan thread-vectors:backfill --limit=500 (a small trial; it prints tokens and cost), then without --limit.');
        } elseif (! ThreadVectorIndex::enabled()) {
            $this->line('The index is complete. Set AI_THREAD_VECTORS_ENABLED=true, then: php artisan config:cache && php artisan queue:restart');
        } else {
            $this->info('The index is complete and live.');
        }

        return self::SUCCESS;
    }
}
