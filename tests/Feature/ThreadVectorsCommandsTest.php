<?php

declare(strict_types=1);

use Filagora\AiForums\Search\ThreadVectorIndex;

beforeEach(function () {
    $this->stack = aiStack();
    config(['filagora-ai-forums.thread_vectors.enabled' => false]);
    $this->stack['engine']->syncSettings((new ThreadVectorIndex)->definition());
});

it('counts what it would embed and sends nothing on a dry run', function () {
    aiThread(replies: ['Reply.']);
    aiThread(title: 'Unanswered');
    $this->stack['embeddings']->batchesEmbedded = 0;

    $this->artisan('thread-vectors:backfill', ['--dry-run' => true])
        ->expectsOutputToContain('1 answered threads')
        ->expectsOutputToContain('Dry run')
        ->assertExitCode(0);

    expect($this->stack['embeddings']->batchesEmbedded)->toBe(0);
});

it('embeds every answered thread and reports tokens and cost', function () {
    aiThread(replies: ['Reply.']);
    aiThread(replies: ['Reply.']);

    $this->artisan('thread-vectors:backfill', ['--sleep-ms' => 0, '--batch-size' => 1])
        ->expectsOutputToContain('Done: 2 threads sent')
        ->assertExitCode(0);

    expect($this->stack['engine']->stats((new ThreadVectorIndex)->definition())->documents)->toBe(2);
});

it('resumes from an id and stops at a limit', function () {
    $threads = [aiThread(replies: ['r']), aiThread(replies: ['r']), aiThread(replies: ['r'])];

    $this->artisan('thread-vectors:backfill', ['--sleep-ms' => 0, '--from-id' => $threads[1]->id, '--limit' => 1])
        ->expectsOutputToContain('1 threads sent')
        ->assertExitCode(0);

    expect($this->stack['engine']->stats((new ThreadVectorIndex)->definition())->documents)->toBe(1);
});

it('says where to continue when the provider fails on a batch', function () {
    aiThread(replies: ['r']);
    aiThread(replies: ['r']);
    $this->stack['embeddings']->failAfterBatches = 1;

    $this->artisan('thread-vectors:backfill', ['--sleep-ms' => 0, '--batch-size' => 1])
        ->expectsOutputToContain('The embedding provider failed on a batch: embeddings are down. Nothing from that batch was sent. Continue with --from-id=')
        ->assertExitCode(1);
});

it('refuses to start while embeddings are off, spending nothing', function () {
    aiThread(replies: ['r']);
    config(['filagora-ai.embeddings.enabled' => false]);

    $this->artisan('thread-vectors:backfill', ['--sleep-ms' => 0])
        ->expectsOutputToContain('Embeddings are switched off')
        ->assertExitCode(1);
});

it('refuses an option that is not a whole number', function () {
    $this->artisan('thread-vectors:backfill', ['--limit' => 'lots'])
        ->expectsOutputToContain('--limit must be a whole number')
        ->assertExitCode(1);
});

it('tells what to run next at each stage', function () {
    aiThread(replies: ['r']);

    $this->artisan('thread-vectors:status')->expectsOutputToContain('Next: php artisan thread-vectors:backfill')->assertExitCode(0);

    $this->artisan('thread-vectors:backfill', ['--sleep-ms' => 0])->assertExitCode(0);
    $this->artisan('thread-vectors:status')->expectsOutputToContain('Set AI_THREAD_VECTORS_ENABLED=true')->assertExitCode(0);

    config(['filagora-ai-forums.thread_vectors.enabled' => true]);
    $this->artisan('thread-vectors:status')->expectsOutputToContain('complete and live')->assertExitCode(0);
});

it('warns that embeddings are off in the status', function () {
    config(['filagora-ai.embeddings.enabled' => false]);

    $this->artisan('thread-vectors:status')->expectsOutputToContain('Embeddings are off')->assertExitCode(0);
});
