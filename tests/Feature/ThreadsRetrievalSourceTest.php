<?php

declare(strict_types=1);

use Filagora\Ai\Retrieval\RetrievalQuery;
use Filagora\Ai\Retrieval\RetrievalSources;
use Filagora\Ai\Services\ChatRetrieval;
use Filagora\AiForums\Retrieval\ThreadsRetrievalSource;
use Filagora\AiForums\Search\ThreadVectorIndex;
use Filagora\AiForums\Tests\Support\HostAccess;
use Filagora\AiForums\Tests\Support\TestUser;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\Thread;
use Filagora\Forums\Support\Abilities;
use Filagora\Search\Indexing\Indexer;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->stack = aiStack();
    $this->stack['engine']->syncSettings((new ThreadVectorIndex)->definition());
    Route::get('/threads/{thread}', fn () => 'ok')->name('threads.show');
    app('router')->getRoutes()->refreshNameLookups();
    $this->stack['embeddings']->vectors = ['Paypal' => [1.0, 0.0], 'Shipping' => [0.0, 1.0]];

    $this->reindex = function (): void {
        $this->stack['engine']->flush((new ThreadVectorIndex)->definition());
        app(Indexer::class)->reindex(new ThreadVectorIndex);
    };

    $this->ask = fn (?TestUser $user, array $vector = [1.0, 0.0], array $options = []): array => app(ThreadsRetrievalSource::class)->retrieve(new RetrievalQuery('how', $vector, $user, null, $options));
});

it('offers the threads closest in meaning, as the question and the replies that answer it', function () {
    aiThread(title: 'Paypal fails', opening: 'Paypal shows an error.', replies: ['Update the module.']);
    aiThread(title: 'Shipping rates', opening: 'Shipping rates are wrong.', replies: ['Check the zone.']);
    ($this->reindex)();

    $items = ($this->ask)(null, [1.0, 0.0], ['limits' => ['threads' => 1]]);

    expect($items)->toHaveCount(1)
        ->and($items[0]['type'])->toBe('thread')
        ->and($items[0]['title'])->toBe('Paypal fails')
        ->and($items[0]['url'])->toEndWith('/threads/'.$items[0]['id'])
        ->and($items[0]['excerpt'])->toContain('Question: Paypal shows an error.')
        ->and($items[0]['excerpt'])->toContain('Reply: Update the module.');
});

it('leaves out a thread the person asking could not open', function () {
    $staffOnly = aiForum(ForumVisibility::STAFF_ONLY);
    aiThread($staffOnly, title: 'Paypal secrets', replies: ['Internal reply.']);
    aiThread(title: 'Paypal public', replies: ['Public reply.']);
    ($this->reindex)();

    $guest = collect(($this->ask)(null))->pluck('title')->all();
    $staff = TestUser::make('Staff');
    HostAccess::$granted[$staff->id] = [Abilities::MODERATE];
    $asStaff = collect(($this->ask)($staff))->pluck('title')->all();

    expect($guest)->toBe(['Paypal public'])
        ->and($asStaff)->toContain('Paypal secrets');
});

it('does not offer the thread it is being asked about', function () {
    $asked = aiThread(title: 'Paypal asked', replies: ['A reply.']);
    aiThread(title: 'Paypal other', replies: ['Another reply.']);
    ($this->reindex)();

    $titles = collect(($this->ask)(null, [1.0, 0.0], ['exclude_thread_id' => $asked->id]))->pluck('title')->all();

    expect($titles)->toBe(['Paypal other']);
});

it('does not offer a thread that lost its last reply since it was indexed', function () {
    $thread = aiThread(title: 'Paypal gone', replies: ['A reply.']);
    ($this->reindex)();
    Thread::query()->whereKey($thread->id)->update(['reply_count' => 0]);

    expect(($this->ask)(null))->toBe([]);
});

it('takes as many as asked for, looking past a few it drops', function () {
    $hidden = aiForum(ForumVisibility::STAFF_ONLY);
    foreach (range(1, 2) as $n) {
        aiThread($hidden, title: "Paypal hidden {$n}", replies: ['r']);
    }
    aiThread(title: 'Paypal visible', replies: ['r']);
    ($this->reindex)();

    expect(collect(($this->ask)(null, [1.0, 0.0], ['limits' => ['threads' => 1]]))->pluck('title')->all())->toBe(['Paypal visible']);
});

it('offers nothing while thread vectors are off', function () {
    aiThread(title: 'Paypal fails', replies: ['A reply.']);
    ($this->reindex)();
    config(['filagora-ai-forums.thread_vectors.enabled' => false]);

    expect(($this->ask)(null))->toBe([]);
});

it('is one of the places the chat looks, after code and documentation', function () {
    expect(array_map(fn ($source): string => $source->key(), app(RetrievalSources::class)->all()))->toContain('threads');
});

it('is searched by the chat as a whole', function () {
    aiThread(title: 'Paypal fails', replies: ['Update the module.']);
    ($this->reindex)();

    $items = app(ChatRetrieval::class)->retrieve('Paypal', null);

    expect(collect($items)->pluck('title')->all())->toContain('Paypal fails');
});
