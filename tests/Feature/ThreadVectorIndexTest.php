<?php

declare(strict_types=1);

use Filagora\Ai\Services\EmbeddingBatchFailed;
use Filagora\AiForums\Search\ThreadVectorIndex;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Models\Thread;
use Filagora\Search\Indexing\Indexer;
use Filagora\Search\Support\SearchQuery;

beforeEach(function () {
    $this->stack = aiStack();
    $this->index = new ThreadVectorIndex;
    $this->stack['engine']->syncSettings($this->index->definition());
});

function vectorsInEngine(): int
{
    return test()->stack['engine']->search((new ThreadVectorIndex)->definition(), new SearchQuery(vector: [0.1, 0.2], perPage: 100))->total;
}

it('is a vector-only index of the forum, as long as the provider\'s vectors', function () {
    $definition = (new ThreadVectorIndex)->definition();

    expect($definition->name)->toBe('thread_vectors')
        ->and($definition->searchable)->toBe([])
        ->and($definition->filterable)->toBe(['forum_id'])
        ->and($definition->vectorDimensions)->toBe(2);
});

it('declares no vectors while embeddings are off', function () {
    config(['filagora-ai.embeddings.enabled' => false]);

    expect((new ThreadVectorIndex)->definition()->vectorDimensions)->toBeNull()
        ->and((new ThreadVectorIndex)->expectedCount())->toBe(0);
});

it('wants approved, answered threads in active forums, and no others', function () {
    $answered = aiThread(replies: ['Try X.']);
    $unanswered = aiThread(title: 'Nobody replied');
    $pending = aiThread(replies: ['Try X.']);
    $pending->forceFill(['moderation_status' => ContentModerationStatus::PENDING])->save();
    $inactive = aiThread(aiForum(active: false), replies: ['Try X.']);

    $wanted = $this->index->query()->pluck('id')->all();

    expect($wanted)->toBe([$answered->id])
        ->and($this->index->shouldBeSearchable($answered))->toBeTrue()
        ->and($this->index->shouldBeSearchable($unanswered))->toBeFalse()
        ->and($this->index->shouldBeSearchable($pending->refresh()))->toBeFalse()
        ->and($this->index->shouldBeSearchable($inactive->load('forum')))->toBeFalse()
        ->and($this->index->expectedCount())->toBe(1);
});

it('embeds the title and the start of the first post, a whole chunk in one call', function () {
    $one = aiThread(title: 'Alpha', opening: 'First question.', replies: ['Reply']);
    $two = aiThread(title: 'Beta', opening: '<p>Second   <b>question</b>.</p>', replies: ['Reply']);
    $this->stack['embeddings']->texts = [];
    $this->stack['embeddings']->batchesEmbedded = 0;
    $this->stack['engine']->flush($this->index->definition());

    $sent = app(Indexer::class)->reindex($this->index, batchSize: 10);

    expect($sent)->toBe(2)
        ->and($this->stack['embeddings']->batchesEmbedded)->toBe(1)
        ->and($this->stack['embeddings']->texts)->toContain("Alpha\n\nFirst question.")
        ->and($this->stack['embeddings']->texts)->toContain("Beta\n\nSecond question.")
        ->and(vectorsInEngine())->toBe(2);
});

it('cuts the first post to the configured length', function () {
    config(['filagora-ai-forums.thread_vectors.first_post_limit' => 10]);
    $thread = aiThread(title: 'T', opening: str_repeat('word ', 50), replies: ['Reply']);

    $text = ThreadVectorIndex::embeddingText($thread, ThreadVectorIndex::firstPostsFor([$thread->id])[$thread->id]);

    expect(mb_strlen($text))->toBeLessThanOrEqual(mb_strlen("T\n\n") + 10);
});

it('uses the first approved, undeleted post, not a later or a hidden one', function () {
    $thread = aiThread(title: 'T', opening: 'Hidden opener', replies: ['Visible reply']);
    $opener = Thread::query()->find($thread->id)->posts()->orderBy('id')->first();
    $opener->forceFill(['is_deleted' => true])->save();

    $first = ThreadVectorIndex::firstPostsFor([$thread->id])[$thread->id];

    expect($first->body)->toBe('Visible reply');
});

it('stops before sending a batch whose embedding failed, rather than index it without vectors', function () {
    aiThread(replies: ['Reply']);
    $this->stack['engine']->flush($this->index->definition());
    $this->stack['embeddings']->fails = true;

    expect(fn () => app(Indexer::class)->reindex($this->index))->toThrow(EmbeddingBatchFailed::class)
        ->and(vectorsInEngine())->toBe(0);
});
